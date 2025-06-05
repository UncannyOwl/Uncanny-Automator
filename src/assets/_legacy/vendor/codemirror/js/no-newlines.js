/**
 * Add a configuration option to CodeMirror called "noNewlines" that when set as
 * 'true' will prevent any newline characters from being typed into the
 * CodeMirror editor and any newline character pasted into the editor will be
 * replaced by a single space.
 *
 * @default false
 */

(function(mod) {
  if (typeof exports === 'object' && typeof module === 'object') {
    // CommonJS
    mod(require('codemirror'))
  } else if (typeof define === 'function' && define.amd) {
    // AMD
    define(['codemirror'], mod)
  } else {
    // Plain browser
    mod(CodeMirror)
  }
})(function(CodeMirror) {
  function beforeChange (cm, event) {
    // Identify typing events that add a newline to the buffer.
    var hasTypedNewline = (
      event.origin ==='+input' &&
      typeof event.text === 'object' &&
      event.text.join('') === '')

    // Prevent newline characters from being added to the buffer.
    if (hasTypedNewline) {
      return event.cancel()
    }

    // Identify paste events.
    var hasPastedNewline = (
      event.origin === 'paste' &&
      typeof event.text === 'object' &&
      event.text.length > 1)

    // Format pasted text to replace newlines with spaces.
    if (hasPastedNewline) {
      var newText = event.text.join(' ')
      return event.update(null, null, [newText])
    }

    return null
  }

  CodeMirror.defineOption('noNewlines', false, function (cm, val, old) {
    // Handle attaching/detaching event listners as necessary.
    if (val === true && old !== true) {
      cm.on('beforeChange', beforeChange)
    } else if (val === false && old === true) {
      cm.off('beforeChange', beforeChange)
    }
  })
})
