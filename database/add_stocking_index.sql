-- Add index on stocking.Barcode for better performance
-- Run this on your POS database to improve stock filtering speed

CREATE INDEX IF NOT EXISTS idx_stocking_barcode ON stocking(Barcode);

-- Optional: Add index on STOCKCURRENT for better in-stock filtering
CREATE INDEX IF NOT EXISTS idx_stockcurrent_product_units ON STOCKCURRENT(PRODUCT, UNITS);