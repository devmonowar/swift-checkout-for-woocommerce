<?php
// Dependencies for edit.js (no build step — hand-written so WordPress
// loads wp.* globals BEFORE our script instead of racing it).
return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
	'version'      => '1.0.0',
);
