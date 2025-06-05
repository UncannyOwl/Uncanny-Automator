# Legacy assets (`/_legacy`)

**WARNING:** This directory contains outdated frontend code and vendor libraries from previous versions of Automator.

## Contents

* Older JavaScript files (pre-ES6 modules, may rely on global variables or jQuery).
* Older CSS files.
* **`/vendor`**: Manually included third-party libraries (e.g., CodeMirror, TinyMCE plugin) that are **not** managed via npm/`package.json`.

## Guidelines

* **DO NOT ADD NEW CODE HERE.** New development must happen within the `/src` directory using modern standards (TypeScript, SCSS Modules, Web Components, npm dependencies).
* **GOAL:** The code in this directory should be progressively **refactored or replaced** with modern equivalents located in `/src` and dependencies managed via `package.json`.
* **CAUTION:** Be aware that code here might use different standards, rely on global scope, or have dependencies on the specific versions of vendor libraries included here. Imports within this directory should primarily use relative paths.

Replacing the vendor libraries with npm-managed versions is highly recommended for better dependency management and security updates.