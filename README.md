📊 WordPress Analytics & Data Visualization Server
This repository contains the custom logic for a WordPress-based analytics dashboard. It integrates external APIs and visualizes data using D3.js modules.

🛠 Server Environment
Platform: WordPress 6.x+

PHP Version: 8.1+ (Standard for modern API handling)

Primary Database: MySQL/MariaDB

Frontend Library: D3.js v7 (Managed via WPCode)

📦 Key Components
1. Custom Analytics Plugin

Located in /plugins/your-plugin-name/.

Purpose: Handles all server-side API calls to fetch analytics data.

Security: API keys are not included in this repo. You must create an api-keys.php file in the plugin root (see api-keys.sample.php).

2. D3.js Modules (WPCode)

The visualization logic is stored in the /snippets/ folder.

Deployment: These scripts are intended to be pasted into WPCode (or a similar snippet manager) on the target site.

Dependencies: Ensure D3.js is enqueued in the header/footer before running these snippets.

🚀 Manual Deployment Steps
Plugins: Upload the your-custom-analytics-plugin folder to your /wp-content/plugins/ directory via SFTP.

API Keys: * Create a file named api-keys.php inside the plugin folder.

Define your constants: define('MY_API_KEY', 'your_value_here');.

Visualizations: * Install the WPCode plugin.

Create a new "JavaScript" snippet for each file in the /snippets/ directory.

Set the insertion point to "Site Wide Footer" or use the provided Shortcodes.

3. High-Level Architecture (For the "Why")
It's helpful to explain how the data flows. Since you're using a plugin for APIs and D3 for the frontend, the architecture looks like this:

Data Layer: The Custom Plugin fetches raw JSON from external APIs and processes it.

Storage Layer: Data is either cached in WordPress Transients or output directly to a localized JS variable.

Presentation Layer: WPCode triggers the D3.js modules to grab that data and render the SVG charts.
