/*global CodeMirror, dotclear */
'use strict';

window.CodeMirror.defineMode('dotclear', function (config) {
  config.readOnly = true;
  return CodeMirror.getMode(config, 'php');
});