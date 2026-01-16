--TODO use fts
select *
from products
where isactive=1 and details like :search
LIMIT :limit OFFSET :offset;
