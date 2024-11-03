# PHP Error Log Viewer

A WordPress plugin that provides an interface to view, manage, and analyze PHP error logs across your WordPress installation.

## Overview

PHP Error Log Viewer simplifies the process of monitoring and debugging PHP errors in WordPress installations. It automatically scans your WordPress installation directory for error logs and presents them in an interactive table.

### Features

- 🔍 Recursively searches for error logs throughout your WordPress installation
- 📊 Interactive table display with sorting and filtering capabilities
- 🗑️ Delete individual error entries or entire log files
- ⚙️ Configurable settings for customizing the search behavior
- 🔗 IDE integration for direct file linking
- 🚀 Efficient handling of large log files

## Settings

Navigate to the Settings tab to configure:

1. **Directory Depth** - How many levels of subdirectories to search (default: 7)
2. **Exclude Directories** - Comma-separated list of directories to exclude from searching (default: node_modules, vendor)
3. **Include Error Logs** - Additional custom error log filenames to include in the search
4. **IDE Integration** - Configure direct file linking to your IDE:
	- Enable/disable file linking
	- Set the file link format (e.g., `http://localhost:63342/api/file/%f:%l`)
	- Configure path replacement for remote/local development

## Usage

Navigate to 'Error Log Viewer' in the WordPress admin menu

### Managing Errors

- Click ❌ to remove individual errors from log files
- Use the 🗑️ button to delete entire log files

### IDE Integration

To enable IDE integration:

1. Enable the "File Link Format" option in settings
2. Enter your IDE's URL format (e.g., `http://localhost:63342/api/file/%f:%l`)
3. Optionally set a path replacement if your local path differs from the server
