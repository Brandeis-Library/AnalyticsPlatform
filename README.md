# 📊 WordPress Analytics & Data Visualization Server

This repository contains the custom infrastructure for a WordPress-based analytics dashboard. It integrates external APIs via a custom plugin and visualizes data using D3.js modules managed through WPCode.

---

## 🛠 Server Environment & Requirements
* **Platform:** WordPress 6.x+
* **PHP Version:** 8.1+ (Standard for modern API handling)
* **Primary Database:** MySQL/MariaDB
* **Frontend Library:** D3.js v7 (Managed via WPCode)
* **Key Dependencies:** * [WPCode](https://wordpress.org/plugins/insert-headers-and-footers/) (For D3.js module injection)
    * Custom Analytics Plugin (Included in `/plugins/`)

---

## 📦 Project Structure

```text
/
├── plugins/
│   └── [your-custom-plugin]/           # Main logic & API integration
│       ├── [your-plugin-main-file].php
│       ├── api-functions.php           # Logic for calling external APIs
│       └── api-keys.sample.php         # TEMPLATE for required keys
├── snippets/                           # D3.js Modules (to be used in WPCode)
│   ├── d3-main-visualization.js        # Core D3 rendering logic
│   └── data-parser-module.js           # Frontend data formatting
└── docs/
    └── server-config.md                # Detailed server notes
