<?php
// This file is part of the local_mcpbridge plugin for Moodle.
//
// Declares the plugin's version and requirements so Moodle can install/upgrade it.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_mcpbridge';   // Full name of the plugin (used for diagnostics).
$plugin->version   = 2026071501;          // The current plugin version (YYYYMMDDXX).
$plugin->requires  = 2023042400;          // Requires Moodle 4.2+ (namespaced external API).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
