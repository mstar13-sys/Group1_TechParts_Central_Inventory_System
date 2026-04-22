-- Speeds up the "Which supplier provides this hardware?" lookup
CREATE INDEX idx_product_supplier_link ON Product_has_Supplier(Product_ID, Supplier_ID);

-- Filter by Category
-- This handles Product -> Category and Stock -> Category filtering
CREATE INDEX idx_product_cat_brand ON Product(Category_ID, Brand);

-- Fast lookup for the "Search Bar" (Specific Product Name)
CREATE INDEX idx_product_name_search ON Product(Name);

-- For the "Smart Fill" to know which supplier has the stock
CREATE INDEX idx_stock_lookup ON Stock(Product_ID, Supplier_ID, Quantity);

-- Composite index for the most common supplier contact lookups
CREATE INDEX idx_supplier_contact_search ON Supplier(Name, Email);

