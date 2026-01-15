--TODO use fts
select count(id) as maxitems
from products
where isactive=1 and details like :search;
