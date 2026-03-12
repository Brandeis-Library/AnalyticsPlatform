# 📊 WordPress Analytics & Data Visualization Server

This repository contains the custom logic for a WordPress-based analytics dashboard. It integrates external APIs and visualizes data using D3.js modules. Brandeis Library deploys WordPress using CloudWays on DigitalOcean, so you may need to adapt these instructions to your particular environment.

---

## 🛠 Server Environment

* **Platform:** WordPress 6.x+
* **PHP Version:** 8.1+ (Standard for modern API handling)
* **Primary Database:** MySQL/MariaDB
* **Frontend Library:** D3.js v7 (Managed via WPCode)

---

## 📦 Key Components

### 1. Custom Analytics Plugin
* **Location:** `/plugins/brandeis-analytics/` (Rename for your particular deployment)
* **Purpose:** Handles all server-side API calls to fetch analytics data.
* **Security:** API keys are **not** included in this repo. On the Brandeis server, API keys are stored securely in a private configuration file. You will need to determine the best file to use or create on your end. Just make sure all are stored in the same place for ease of reference.

### 2. D3.js Modules (WPCode)
* **Location:** The visualization logic is stored in the `/snippets/` folder.
* **Deployment:** These scripts are intended to be pasted into **WPCode** (or a similar snippet manager) on the target site.
* **Dependencies:** Ensure D3.js is enqueued in the header/footer before running these snippets.

---

## 🚀 Manual Deployment Steps

1. **Plugins:** Upload the `your-custom-analytics-plugin` folder to your `/wp-content/plugins/` directory or similar depending on your hosting and/or deployment via SFTP.
2. **API Keys:** * Create a file named `api-keys.php` (or pick a secure configuration file) inside the plugin folder.
    * Define your constants: `define('MY_API_KEY', 'your_value_here');`
3. **Visualizations:** * Install the **WPCode** plugin.
    * Create a new "JavaScript" snippet for each file in the `/snippets/` directory.
    * Set the insertion point to use the provided Shortcodes.

---

## 3. High-Level Architecture

It's helpful to explain how the data flows:

* **Data Layer:** The Custom Plugin fetches raw JSON from external APIs and processes it.
* **Storage Layer:** Data is either cached in WordPress Transients or output directly to a localized JS variable.
* **Presentation Layer:** WPCode triggers the D3.js modules to grab that data and render the SVG charts.
