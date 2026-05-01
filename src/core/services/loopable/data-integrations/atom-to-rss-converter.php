<?php
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Atom_To_Rss_Converter
 *
 * Rewrites an ATOM 1.0 feed into an RSS 2.0 feed so the existing
 * `Xml_To_Json_Converter` pipeline (which was written against RSS) can parse
 * it without any further awareness of ATOM. Non-ATOM namespaced children
 * (yt:*, media:*, content:*, custom publisher extensions) are copied through
 * verbatim so downstream token discovery still reaches them.
 *
 * @package Uncanny_Automator\Services\Loopable\Data_Integrations
 */
class Atom_To_Rss_Converter {

	const ATOM_NS    = 'http://www.w3.org/2005/Atom';
	const CONTENT_NS = 'http://purl.org/rss/1.0/modules/content/';
	const XMLNS_NS   = 'http://www.w3.org/2000/xmlns/';

	/**
	 * Delimiter used to join per-entry multi-valued ATOM leaves (authors,
	 * contributors, categories) into a single flat token value. Comma-space
	 * for readability; in practice ATOM category terms and person names
	 * almost never contain commas, and this is a display token — parse
	 * reliability isn't a goal here.
	 */
	const FLAT_DELIMITER = ', ';

	/**
	 * ATOM local names whose value we already emit under an RSS-native element,
	 * or which we fold into a flat top-level token (`authors`, `contributors`,
	 * `categories`). Skipped in the verbatim passthrough so we don't produce
	 * duplicate `atom:*` tokens alongside their flat/RSS equivalents, and
	 * don't expose picker entries whose shape varies entry-to-entry.
	 */
	const ATOM_HANDLED_LOCAL_NAMES = array(
		'id',
		'title',
		'link',
		'published',
		'updated',
		'summary',
		'content',
		'author',
		'contributor',
		'category',
	);

	/**
	 * Flat per-entry token keys this converter emits on every `<item>` so
	 * downstream callers always see the same picker shape regardless of
	 * which ATOM cardinality variants the source feed exposes. Kept on the
	 * converter so producer (this class) and empty-state normaliser
	 * (`normalize_flat_tokens`) can never drift.
	 */
	const FLAT_TOKEN_KEYS = array( 'authors', 'contributors', 'categories' );

	/**
	 * Fast sniff of the XML head: does this look like an ATOM feed?
	 *
	 * @param string $xml
	 *
	 * @return bool
	 */
	public static function is_atom( $xml ) {

		if ( ! is_string( $xml ) || '' === $xml ) {
			return false;
		}

		$head = substr( $xml, 0, 1024 );

		if ( false === stripos( $head, '<feed' ) ) {
			return false;
		}

		return false !== strpos( $head, self::ATOM_NS );
	}

	/**
	 * Returns the input unchanged when it's not ATOM, or the RSS 2.0
	 * equivalent when it is. Centralises the sniff-and-convert pattern
	 * that every caller (analyze, action runtime, trigger runtime) needs.
	 *
	 * Conversion failure for ATOM input falls back to the original body
	 * rather than throwing — runtime callers (action/trigger) don't all
	 * wrap this in try/catch, and the downstream `Xml_To_Json_Converter`
	 * already produces the canonical "this XML is unparseable" error
	 * signal. Analyze paths that need the parse detail call `convert()`
	 * directly and keep their existing try/catch.
	 *
	 * @param string $xml
	 *
	 * @return string
	 */
	public static function maybe_convert( $xml ) {

		$xml = (string) $xml;

		if ( ! self::is_atom( $xml ) ) {
			return $xml;
		}

		try {
			return ( new self() )->convert( $xml );
		} catch ( RuntimeException $e ) {
			return $xml;
		}
	}

	/**
	 * Guarantees the three flat keys this converter emits
	 * (`authors`, `contributors`, `categories`) carry a `_loopable_xml_text`
	 * entry on every item so the hydrator resolves to a string instead of
	 * the `[[]]` wrapper that `Xml_To_Json_Converter` produces for empty
	 * text nodes. Lives next to the converter rather than in pro because
	 * the empty-state shape is a property of how this class emits the
	 * elements — keeping producer and normaliser together prevents drift.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public static function normalize_flat_tokens( array $items ) {

		foreach ( $items as &$item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			foreach ( self::FLAT_TOKEN_KEYS as $key ) {
				$inner = $item[ $key ][0] ?? null;
				if ( is_array( $inner ) && isset( $inner['_loopable_xml_text'] ) ) {
					continue;
				}
				$item[ $key ] = array( array( '_loopable_xml_text' => '' ) );
			}
		}
		unset( $item );

		return $items;
	}

	/**
	 * Converts the given ATOM XML string to RSS 2.0 XML.
	 *
	 * @param string $atom_xml
	 *
	 * @return string
	 *
	 * @throws RuntimeException If the source cannot be parsed.
	 */
	public function convert( $atom_xml ) {

		$src = new DOMDocument();
		$src->preserveWhiteSpace = false;

		$previous = libxml_use_internal_errors( true );
		$loaded   = $src->loadXML( $atom_xml );
		$errors   = $loaded ? array() : libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || null === $src->documentElement ) {
			$detail = ! empty( $errors ) ? trim( $errors[0]->message ) : 'unknown';
			throw new RuntimeException( 'Could not parse ATOM feed as XML: ' . $detail );
		}

		$atom_root = $src->documentElement;

		$dst = new DOMDocument( '1.0', 'UTF-8' );
		$dst->formatOutput = true;

		$rss = $dst->createElement( 'rss' );
		$rss->setAttribute( 'version', '2.0' );

		// Always declare the RSS modules we emit into.
		$rss->setAttributeNS( self::XMLNS_NS, 'xmlns:content', self::CONTENT_NS );

		// Forward any non-ATOM namespaces the source declared so nested
		// children (yt:*, media:*, itunes:*, etc.) keep their prefix on the
		// output root and remain addressable by downstream token discovery.
		foreach ( $this->extract_non_atom_namespaces( $atom_root ) as $prefix => $uri ) {
			$rss->setAttributeNS( self::XMLNS_NS, 'xmlns:' . $prefix, $uri );
		}

		$channel = $dst->createElement( 'channel' );

		$channel->appendChild( $this->make_text_element( $dst, 'title', $this->atom_child_text( $atom_root, 'title' ) ) );

		$channel_link = $this->atom_alternate_href( $atom_root );
		if ( '' !== $channel_link ) {
			$channel->appendChild( $this->make_text_element( $dst, 'link', $channel_link ) );
		}

		$channel->appendChild( $this->make_text_element( $dst, 'description', $this->atom_child_text( $atom_root, 'subtitle' ) ) );

		foreach ( $this->atom_children( $atom_root, 'entry' ) as $entry ) {
			$channel->appendChild( $this->convert_entry( $entry, $dst ) );
		}

		$rss->appendChild( $channel );
		$dst->appendChild( $rss );

		return $dst->saveXML();
	}

	/**
	 * Produces an RSS `<item>` from a single ATOM `<entry>`.
	 *
	 * @param DOMElement   $entry
	 * @param DOMDocument  $dst
	 *
	 * @return DOMElement
	 */
	private function convert_entry( DOMElement $entry, DOMDocument $dst ) {

		$item = $dst->createElement( 'item' );

		// <title>
		$item->appendChild( $this->make_text_element( $dst, 'title', $this->atom_child_text( $entry, 'title' ) ) );

		// <link> — prefer rel="alternate", fall back to first link with href.
		$link = $this->atom_alternate_href( $entry );
		if ( '' !== $link ) {
			$item->appendChild( $this->make_text_element( $dst, 'link', $link ) );
		}

		// <guid isPermaLink="false"> from atom:id
		$id = $this->atom_child_text( $entry, 'id' );
		if ( '' !== $id ) {
			$guid = $this->make_text_element( $dst, 'guid', $id );
			$guid->setAttribute( 'isPermaLink', 'false' );
			$item->appendChild( $guid );
		}

		// <pubDate> from atom:published (fallback: atom:updated). RFC 3339 → RFC 822.
		$published = $this->atom_child_text( $entry, 'published' );
		if ( '' === $published ) {
			$published = $this->atom_child_text( $entry, 'updated' );
		}
		if ( '' !== $published ) {
			$timestamp = strtotime( $published );
			if ( false !== $timestamp ) {
				$item->appendChild( $this->make_text_element( $dst, 'pubDate', gmdate( DATE_RSS, $timestamp ) ) );
			}
		}

		// <description> from atom:summary, fallback to atom:content as plain text.
		// Only emitted when non-empty so an absent ATOM summary/content does not
		// produce an empty downstream token array.
		$summary = $this->atom_child_text( $entry, 'summary' );
		if ( '' === $summary ) {
			$summary = $this->atom_child_text( $entry, 'content' );
		}
		if ( '' !== $summary ) {
			$item->appendChild( $this->make_text_element( $dst, 'description', $summary ) );
		}

		// <content:encoded> from atom:content — always CDATA-wrapped so HTML survives.
		$content_element = $this->atom_first_child( $entry, 'content' );
		if ( null !== $content_element ) {
			$content_value = $this->atom_content_value( $content_element );
			if ( '' !== $content_value ) {
				$content_encoded = $dst->createElementNS( self::CONTENT_NS, 'content:encoded' );
				$content_encoded->appendChild( $dst->createCDATASection( $this->escape_cdata( $content_value ) ) );
				$item->appendChild( $content_encoded );
			}
		}

		// Flat per-entry tokens for the three ATOM elements whose cardinality
		// varies entry-to-entry with no clean way to expose that variance in
		// the picker. ATOM RFC 4287 defines the structure of each precisely
		// (§4.2.1/§4.2.2/§4.2.3) so the leaves we join on — `atom:name` for
		// person constructs, `@term` for categories — are spec-stable.
		// Emitted even when empty so the picker always offers the token;
		// empty items hydrate to the empty string.
		$item->appendChild( $this->make_flat_element( $dst, 'authors', $this->join_atom_person_names( $entry, 'author' ) ) );
		$item->appendChild( $this->make_flat_element( $dst, 'contributors', $this->join_atom_person_names( $entry, 'contributor' ) ) );
		$item->appendChild( $this->make_flat_element( $dst, 'categories', $this->join_atom_category_terms( $entry ) ) );

		// Copy every non-ATOM child verbatim so no source data is lost.
		// ATOM children are intentionally NOT preserved — the RSS-native
		// mapping above plus the three flat tokens cover every leaf the spec
		// guarantees. Preserving `atom:*` as well would re-introduce the
		// per-entry shape variance we just flattened away.
		foreach ( $entry->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			if ( self::ATOM_NS === $child->namespaceURI ) {
				if ( in_array( $child->localName, self::ATOM_HANDLED_LOCAL_NAMES, true ) ) {
					// Already emitted as an RSS-native element or flat
					// token above — skip to avoid duplicates.
					continue;
				}
				$item->appendChild( $this->clone_with_atom_prefix( $child, $dst ) );
				continue;
			}
			$item->appendChild( $dst->importNode( $child, true ) );
		}

		return $item;
	}

	/**
	 * Joins the `atom:name` text of every direct person-construct child
	 * (`atom:author` or `atom:contributor`, per RFC 4287 §4.2.1/§4.2.3)
	 * into a single delimited string. Entries with no name are skipped.
	 *
	 * @param DOMElement $entry
	 * @param string     $local_name Either `'author'` or `'contributor'`.
	 *
	 * @return string
	 */
	private function join_atom_person_names( DOMElement $entry, $local_name ) {

		$names = array();
		foreach ( $this->atom_children( $entry, $local_name ) as $person ) {
			$name = $this->atom_child_text( $person, 'name' );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return implode( self::FLAT_DELIMITER, $names );
	}

	/**
	 * Joins the `term` attribute of every direct `atom:category` child
	 * (RFC 4287 §4.2.2) into a single delimited string. Categories without
	 * a term are skipped (term is required by the spec but defensive).
	 *
	 * @param DOMElement $entry
	 *
	 * @return string
	 */
	private function join_atom_category_terms( DOMElement $entry ) {

		$terms = array();
		foreach ( $this->atom_children( $entry, 'category' ) as $category ) {
			$term = (string) $category->getAttribute( 'term' );
			if ( '' !== $term ) {
				$terms[] = $term;
			}
		}
		return implode( self::FLAT_DELIMITER, $terms );
	}

	/**
	 * Creates a text-bearing element that always serialises with open/close
	 * tags (even when the text is empty) so the downstream parser sees
	 * `_loopable_xml_text` consistently.
	 *
	 * @param DOMDocument $doc
	 * @param string      $name
	 * @param string      $text
	 *
	 * @return DOMElement
	 */
	private function make_flat_element( DOMDocument $doc, $name, $text ) {

		$element = $doc->createElement( $name );
		$element->appendChild( $doc->createTextNode( (string) $text ) );
		return $element;
	}

	/**
	 * Clones an ATOM element into the destination document under an explicit
	 * `atom:` prefix so the downstream xpath/namespace-aware converter sees
	 * `<atom:author>` etc. rather than something anchored to the default
	 * namespace (which the output document doesn't declare).
	 *
	 * @param \DOMNode     $source
	 * @param DOMDocument  $dst
	 *
	 * @return \DOMNode
	 */
	private function clone_with_atom_prefix( $source, DOMDocument $dst ) {

		if ( XML_ELEMENT_NODE !== $source->nodeType ) {
			return $dst->importNode( $source, true );
		}

		$element = $dst->createElementNS( self::ATOM_NS, 'atom:' . $source->localName );

		foreach ( $source->attributes as $attr ) {
			$element->setAttribute( $attr->name, $attr->value );
		}

		foreach ( $source->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && self::ATOM_NS === $child->namespaceURI ) {
				$element->appendChild( $this->clone_with_atom_prefix( $child, $dst ) );
				continue;
			}
			$element->appendChild( $dst->importNode( $child, true ) );
		}

		return $element;
	}

	/**
	 * Collects non-ATOM namespace declarations from the source root element
	 * so they can be replicated on the output root.
	 *
	 * @param DOMElement $atom_root
	 *
	 * @return array<string, string>
	 */
	private function extract_non_atom_namespaces( DOMElement $atom_root ) {

		$namespaces = array();

		$xpath      = new DOMXPath( $atom_root->ownerDocument );
		$ns_nodes   = $xpath->query( 'namespace::*', $atom_root );

		if ( false === $ns_nodes ) {
			return $namespaces;
		}

		foreach ( $ns_nodes as $ns_node ) {
			$prefix = $ns_node->localName;
			$uri    = $ns_node->namespaceURI;

			if ( '' === $prefix || 'xml' === $prefix || 'xmlns' === $prefix ) {
				continue;
			}

			if ( self::ATOM_NS === $uri ) {
				continue;
			}

			// Don't override the reserved one we always declare.
			if ( 'content' === $prefix ) {
				continue;
			}

			$namespaces[ $prefix ] = $uri;
		}

		return $namespaces;
	}

	/**
	 * Returns the text of the first direct ATOM child of `$parent` with the
	 * given local name (e.g. "title", "id", "summary"). Empty string if
	 * missing.
	 *
	 * @param DOMElement $parent
	 * @param string     $local_name
	 *
	 * @return string
	 */
	private function atom_child_text( DOMElement $parent, $local_name ) {

		$child = $this->atom_first_child( $parent, $local_name );
		if ( null === $child ) {
			return '';
		}

		return $this->atom_content_value( $child );
	}

	/**
	 * Returns the first direct child of `$parent` in the ATOM namespace with
	 * the given local name.
	 *
	 * @param DOMElement $parent
	 * @param string     $local_name
	 *
	 * @return DOMElement|null
	 */
	private function atom_first_child( DOMElement $parent, $local_name ) {

		foreach ( $this->atom_children( $parent, $local_name ) as $child ) {
			return $child;
		}

		return null;
	}

	/**
	 * Yields all direct children of `$parent` in the ATOM namespace with the
	 * given local name.
	 *
	 * @param DOMElement $parent
	 * @param string     $local_name
	 *
	 * @return \Generator<DOMElement>
	 */
	private function atom_children( DOMElement $parent, $local_name ) {

		foreach ( $parent->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			if ( self::ATOM_NS !== $child->namespaceURI ) {
				continue;
			}
			if ( $child->localName !== $local_name ) {
				continue;
			}
			yield $child;
		}
	}

	/**
	 * Picks the `href` of the first `<atom:link>` whose `rel="alternate"`,
	 * falling back to the first link that carries an href at all.
	 *
	 * @param DOMElement $parent
	 *
	 * @return string
	 */
	private function atom_alternate_href( DOMElement $parent ) {

		$first_href = '';

		foreach ( $this->atom_children( $parent, 'link' ) as $link ) {
			$href = (string) $link->getAttribute( 'href' );
			if ( '' === $href ) {
				continue;
			}
			if ( '' === $first_href ) {
				$first_href = $href;
			}
			$rel = (string) $link->getAttribute( 'rel' );
			if ( '' === $rel || 'alternate' === $rel ) {
				return $href;
			}
		}

		return $first_href;
	}

	/**
	 * Extracts the usable text value of an ATOM element, handling the three
	 * `type` variants of `<content>` / `<summary>` / `<title>`:
	 * - `type="text"` (default): the element's plain text.
	 * - `type="html"`: escaped HTML — returned as-is, already decoded by the
	 *   XML parser.
	 * - `type="xhtml"`: an inline XHTML `<div>` wrapper — returns the
	 *   serialised inner HTML of that div.
	 *
	 * @param DOMElement $element
	 *
	 * @return string
	 */
	private function atom_content_value( DOMElement $element ) {

		$type = strtolower( (string) $element->getAttribute( 'type' ) );

		if ( 'xhtml' === $type ) {
			$html = '';
			foreach ( $element->childNodes as $child ) {
				if ( XML_ELEMENT_NODE === $child->nodeType && 'div' === $child->localName ) {
					foreach ( $child->childNodes as $inner ) {
						$html .= $element->ownerDocument->saveXML( $inner );
					}
					break;
				}
			}
			return trim( $html );
		}

		return trim( (string) $element->textContent );
	}

	/**
	 * Creates a new text-bearing element on `$doc`, using `createTextNode` so
	 * entity escaping is handled by the DOM layer.
	 *
	 * @param DOMDocument $doc
	 * @param string      $name
	 * @param string      $text
	 *
	 * @return DOMElement
	 */
	private function make_text_element( DOMDocument $doc, $name, $text ) {

		$element = $doc->createElement( $name );
		if ( '' !== $text ) {
			$element->appendChild( $doc->createTextNode( $text ) );
		}
		return $element;
	}

	/**
	 * Escapes a raw `]]>` sequence so it can safely live inside a CDATA
	 * section. Rare enough in practice, but prevents the output from closing
	 * the CDATA early if a feed ever embeds that exact byte sequence in its
	 * content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	private function escape_cdata( $content ) {
		return str_replace( ']]>', ']]]]><![CDATA[>', $content );
	}
}
