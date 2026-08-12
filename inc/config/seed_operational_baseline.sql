-- Operational baseline seed for ANTOBELL
-- Safe to run multiple times (insert-if-missing)

INSERT INTO vendor(fullName, email, mobile, status)
SELECT 'ANTOBELL Main Supplier', 'supplier@antobell.local', '08030000001', 'Active'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM vendor WHERE fullName = 'ANTOBELL Main Supplier'
);

INSERT INTO vendor(fullName, email, mobile, status)
SELECT 'Lagos Bulk Hub', 'lagos.bulk@antobell.local', '08030000002', 'Active'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM vendor WHERE fullName = 'Lagos Bulk Hub'
);

INSERT INTO customer(fullName, email, mobile, address, status)
SELECT 'Walk-in Customer', 'walkin@antobell.local', '08031000001', 'Lagos', 'Active'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM customer WHERE fullName = 'Walk-in Customer'
);

INSERT INTO customer(fullName, email, mobile, address, status)
SELECT 'Retail Partner A', 'partnera@antobell.local', '08031000002', 'Ikeja', 'Active'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM customer WHERE fullName = 'Retail Partner A'
);

INSERT INTO customer(fullName, email, mobile, address, status)
SELECT 'Retail Partner B', 'partnerb@antobell.local', '08031000003', 'Yaba', 'Active'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM customer WHERE fullName = 'Retail Partner B'
);

INSERT INTO item(itemNumber, itemName, unitAsSold, discount, stock, unitPrice, imageURL, status, description)
SELECT 'ANT-USB-C-001', 'ANTOBELL USB-C Cable 1m', 'pcs', 0, 25, 3500, 'imageNotAvailable.jpg', 'Active', 'Baseline seeded item'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM item WHERE itemNumber = 'ANT-USB-C-001'
);

INSERT INTO item(itemNumber, itemName, unitAsSold, discount, stock, unitPrice, imageURL, status, description)
SELECT 'ANT-CHG-020', 'ANTOBELL Fast Charger 20W', 'pcs', 0, 18, 12500, 'imageNotAvailable.jpg', 'Active', 'Baseline seeded item'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM item WHERE itemNumber = 'ANT-CHG-020'
);

INSERT INTO item(itemNumber, itemName, unitAsSold, discount, stock, unitPrice, imageURL, status, description)
SELECT 'ANT-PBANK-10K', 'ANTOBELL Power Bank 10000mAh', 'pcs', 0, 12, 22000, 'imageNotAvailable.jpg', 'Active', 'Baseline seeded item'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM item WHERE itemNumber = 'ANT-PBANK-10K'
);

INSERT INTO item(itemNumber, itemName, unitAsSold, discount, stock, unitPrice, imageURL, status, description)
SELECT 'ANT-ADP-MULTI', 'ANTOBELL Multi Adapter', 'pcs', 0, 20, 8000, 'imageNotAvailable.jpg', 'Active', 'Baseline seeded item'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM item WHERE itemNumber = 'ANT-ADP-MULTI'
);

-- Optional quick checks
SELECT COUNT(*) AS item_count FROM item;
SELECT COUNT(*) AS vendor_count FROM vendor;
SELECT COUNT(*) AS customer_count FROM customer;
