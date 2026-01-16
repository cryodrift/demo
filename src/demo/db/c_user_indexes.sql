CREATE UNIQUE INDEX IF NOT EXISTS one_active_cart
    ON cart (isactive)
    WHERE isactive = 1;
