<?php
/**
 * Static export admin download script.
 *
 * The control plane builds the export artifacts. This presentation helper only
 * packages that response into a ZIP so the admin UI can download one file.
 */

defined('ABSPATH') || exit;
?>
<script>
(function () {
    // ── Control invocation ─────────────────────────────────────
    document.addEventListener('click', function (event) {
        var target = event.target;
        var button = target && target.closest
            ? target.closest('[data-upb-admin-control-id][data-upb-admin-control-url]')
            : null;

        if (!button) {
            return;
        }

        event.preventDefault();
        invokeExportControl(button);
    });

    function invokeExportControl(button) {
        var originalLabel = button.textContent || '';
        var status = findStatusElement(button);

        setStatus(status, '');

        if (!window.fetch) {
            setStatus(status, 'This browser cannot run the static export.');
            return;
        }

        button.disabled = true;
        button.textContent = button.getAttribute('data-upb-busy-label') || 'Exporting...';

        fetch(button.getAttribute('data-upb-admin-control-url') || '', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': button.getAttribute('data-upb-rest-nonce') || ''
            },
            body: JSON.stringify({
                page_id: parseInt(button.getAttribute('data-upb-page-id') || '0', 10)
            })
        })
            .then(readJsonResponse)
            .then(function (payload) {
                var exportData = readExportData(payload);
                var zip = buildZipBlob(exportData.artifacts || []);
                var filename = exportFilename(button, exportData.page_id || button.getAttribute('data-upb-page-id'));

                downloadBlob(zip, filename);
                button.textContent = button.getAttribute('data-upb-success-label') || 'Downloaded';
                window.setTimeout(function () {
                    button.textContent = originalLabel;
                    button.disabled = false;
                }, 1500);
            })
            .catch(function (error) {
                setStatus(status, error && error.message ? error.message : 'Static export failed.');
                button.textContent = originalLabel;
                button.disabled = false;
            });
    }

    function readJsonResponse(response) {
        return response.json().catch(function () {
            return {};
        }).then(function (payload) {
            if (!response.ok) {
                throw new Error(payload.message || payload.code || 'Static export failed.');
            }

            return payload;
        });
    }

    function readExportData(payload) {
        var exportData = payload && payload.data && payload.data.export ? payload.data.export : null;
        if (!exportData && payload && payload.export) {
            exportData = payload.export;
        }
        if (!exportData || !Array.isArray(exportData.artifacts)) {
            throw new Error('Static export response did not include download files.');
        }

        return exportData;
    }

    function findStatusElement(button) {
        var parent = button.parentElement;
        return parent ? parent.querySelector('.upb-admin-control-status') : document.querySelector('.upb-admin-control-status');
    }

    function setStatus(status, message) {
        if (status) {
            status.textContent = message;
        }
    }

    function exportFilename(button, pageId) {
        var base = sanitizeFilename(button.getAttribute('data-upb-download-name') || 'uncanny-page-builder-export');
        var suffix = pageId ? '-' + sanitizeFilename(String(pageId)) : '';

        return base + suffix + '.zip';
    }

    function sanitizeFilename(value) {
        return String(value || '')
            .replace(/[^A-Za-z0-9_.-]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'export';
    }

    function downloadBlob(blob, filename) {
        var url = window.URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 1000);
    }

    // ── ZIP packaging ───────────────────────────────────────────
    function buildZipBlob(artifacts) {
        if (!window.TextEncoder) {
            throw new Error('This browser cannot package the static export.');
        }

        var encoder = new TextEncoder();
        var chunks = [];
        var records = [];
        var offset = 0;

        artifacts.forEach(function (artifact) {
            var path = normalizeArtifactPath(artifact.path || '');
            if (!path) {
                return;
            }

            var nameBytes = encoder.encode(path);
            var dataBytes = encoder.encode(String(artifact.content || ''));
            var crc = crc32(dataBytes);
            var localHeader = zipLocalHeader(nameBytes.length, dataBytes.length, crc);

            chunks.push(localHeader, nameBytes, dataBytes);
            records.push({
                nameBytes: nameBytes,
                crc: crc,
                size: dataBytes.length,
                offset: offset
            });

            offset += localHeader.length + nameBytes.length + dataBytes.length;
        });

        var centralOffset = offset;
        records.forEach(function (record) {
            var centralHeader = zipCentralHeader(record.nameBytes.length, record.size, record.crc, record.offset);
            chunks.push(centralHeader, record.nameBytes);
            offset += centralHeader.length + record.nameBytes.length;
        });

        chunks.push(zipEndOfCentralDirectory(records.length, offset - centralOffset, centralOffset));

        return new Blob(chunks, { type: 'application/zip' });
    }

    function normalizeArtifactPath(path) {
        var parts = String(path).replace(/\\/g, '/').split('/');
        var clean = [];

        parts.forEach(function (part) {
            if (part && part !== '.' && part !== '..') {
                clean.push(part);
            }
        });

        return clean.join('/');
    }

    function zipLocalHeader(nameLength, size, crc) {
        var bytes = new Uint8Array(30);
        var view = new DataView(bytes.buffer);

        view.setUint32(0, 0x04034b50, true);
        view.setUint16(4, 20, true);
        view.setUint16(6, 0x0800, true);
        view.setUint16(8, 0, true);
        view.setUint16(10, 0, true);
        view.setUint16(12, 0, true);
        view.setUint32(14, crc, true);
        view.setUint32(18, size, true);
        view.setUint32(22, size, true);
        view.setUint16(26, nameLength, true);
        view.setUint16(28, 0, true);

        return bytes;
    }

    function zipCentralHeader(nameLength, size, crc, localOffset) {
        var bytes = new Uint8Array(46);
        var view = new DataView(bytes.buffer);

        view.setUint32(0, 0x02014b50, true);
        view.setUint16(4, 20, true);
        view.setUint16(6, 20, true);
        view.setUint16(8, 0x0800, true);
        view.setUint16(10, 0, true);
        view.setUint16(12, 0, true);
        view.setUint16(14, 0, true);
        view.setUint32(16, crc, true);
        view.setUint32(20, size, true);
        view.setUint32(24, size, true);
        view.setUint16(28, nameLength, true);
        view.setUint16(30, 0, true);
        view.setUint16(32, 0, true);
        view.setUint16(34, 0, true);
        view.setUint16(36, 0, true);
        view.setUint32(38, 0, true);
        view.setUint32(42, localOffset, true);

        return bytes;
    }

    function zipEndOfCentralDirectory(recordCount, centralSize, centralOffset) {
        var bytes = new Uint8Array(22);
        var view = new DataView(bytes.buffer);

        view.setUint32(0, 0x06054b50, true);
        view.setUint16(4, 0, true);
        view.setUint16(6, 0, true);
        view.setUint16(8, recordCount, true);
        view.setUint16(10, recordCount, true);
        view.setUint32(12, centralSize, true);
        view.setUint32(16, centralOffset, true);
        view.setUint16(20, 0, true);

        return bytes;
    }

    var CRC_TABLE = makeCrcTable();

    function makeCrcTable() {
        var table = [];
        var c;
        var n;
        var k;

        for (n = 0; n < 256; n += 1) {
            c = n;
            for (k = 0; k < 8; k += 1) {
                c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
            }
            table[n] = c >>> 0;
        }

        return table;
    }

    function crc32(bytes) {
        var crc = 0xffffffff;
        var i;

        for (i = 0; i < bytes.length; i += 1) {
            crc = CRC_TABLE[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
        }

        return (crc ^ 0xffffffff) >>> 0;
    }
}());
</script>
