# Inventory Management System

A simple PHP web system for managing an inventory.  
  

## Installation
* Clone the repository and move the root folder to the deployment folder of your browser. (for Apache, this is htdocs)
* Create a blank DB called *shop_inventory* in MySQL
* Load the sql dump to the newly created _shop_inventory_ database
* Change the root url of your website in [constants.php](inc/config/constants.php) file

## Requirements
* PHP
* MySQL
* Apache
* Google Chrome web browser (JavaScript enabled)
* Internet connection with a reasonable speed

## Usage
* Access the login.php file from via browser and give _guest_ as username and _1234_ as password

## Production Readiness Assessment
Current status: **Partially ready** for a small or medium business pilot, but **not fully business-grade yet**.

### What is already working well
* Core modules exist: items, purchase, sales, customer credit book, reporting, receipts, dashboard KPI, and low-stock alerts.
* Transaction-oriented sales and receipts are in place.
* Recent UI and workflow improvements reduce operator errors (autocomplete, better alerts, transaction ID reprint, low stock page).

### Must-fix before full production
* Strengthen data integrity controls for edit/delete/payment reversal flows.
* Add role-based access controls and stronger security hardening.
* Standardize database schema migration and avoid runtime schema drift.
* Implement operational backups and restore testing.
* Add automated regression testing for purchase, sales, credit, and receipt workflows.

## 7-Day Go-Live Stabilization Checklist
### Day 1
* Lock and document DB schema versions.
* Capture baseline backup and verify restore on a test environment.

### Day 2
* Review all create/update/delete flows for stock and credit accuracy.
* Add audit logging for critical actions (sale, purchase, payment, delete).

### Day 3
* Enforce user roles (admin, cashier, viewer).
* Restrict access to sensitive actions and pages.

### Day 4
* Add input validation consistency checks on server side.
* Remove remaining runtime warnings/notices in logs.

### Day 5
* Execute end-to-end tests:
	* purchase -> stock update
	* sale -> stock out -> receipt
	* credit payment -> balance update -> receipt reprint

### Day 6
* Run load and reliability checks for concurrent usage.
* Verify low stock alerts and dashboard refresh under active transactions.

### Day 7
* Final UAT signoff with business users.
* Freeze release, tag build, and deploy with rollback plan.


## Acknowledgments
* Inspired by many similar projects online



1. Schema changes plus backfill to storeID = 1
2. Session default store and navbar switcher
3. Core transaction scoping: dashboard, items, customers, vendors
4. Purchases, sales, credits, reports
5. Store-specific settings and branch management