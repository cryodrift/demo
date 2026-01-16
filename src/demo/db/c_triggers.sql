-- Ensure only allowed status values can be set on orderitems.status via updates
-- Allowed values mirror Repository::CART_STATUS

DROP TRIGGER IF EXISTS orderitems_status_check_update;
CREATE TRIGGER orderitems_status_check_update
BEFORE UPDATE OF status ON orderitems
FOR EACH ROW
WHEN NEW.status NOT IN (
    'finished',
    'processing'
)
BEGIN
    SELECT RAISE(FAIL, 'Wrong Status: ');
END;

--END;



-- Ensure products.stock cannot be set to a negative value via updates
DROP TRIGGER IF EXISTS products_stock_nonnegative_check_update;
CREATE TRIGGER products_stock_nonnegative_check_update
BEFORE UPDATE OF stock ON products
FOR EACH ROW
WHEN NEW.stock < 0
BEGIN
    SELECT RAISE(FAIL, 'Stock cannot be negative');
END;


