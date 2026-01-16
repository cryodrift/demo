WITH p AS (SELECT json_extract(details, '$.genre') AS genre
           FROM products
           where isactive = 1)
SELECT DISTINCT genre
FROM p
WHERE genre IS NOT NULL
  AND genre != '';
