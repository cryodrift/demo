create table if not exists products
(
    id       INTEGER PRIMARY KEY,
    cartid   TEXT UNIQUE,
    slug     TEXT    default '',
    details  TEXT,
    tplprev  TEXT    default '',
    tplfull  TEXT    default '',
    price    NUMERIC DEFAULT 0 NOT NULL,
    currency TEXT    DEFAULT 'EUR',
    stock    INTEGER DEFAULT 0 NOT NULL,
    checkout INTEGER DEFAULT 0 NOT NULL,
    sold     INTEGER DEFAULT 0 NOT NULL,
    isactive INTEGER DEFAULT 0 NOT NULL,
    isgroup  INTEGER DEFAULT 0 NOT NULL,
    deleted  TEXT    DEFAULT 'n',
    changed  NUMERIC,
    created  NUMERIC DEFAULT CURRENT_TIMESTAMP
);

--  group products into a package of products
create table if not exists products_products
(
    id          INTEGER PRIMARY KEY,
    productsid1 INTEGER,
    productsid2 INTEGER
);

create table if not exists orders
(
    id           INTEGER PRIMARY KEY,
    user         TEXT,
    ordernr      TEXT UNIQUE        NOT NULL,
    total        NUMERIC DEFAULT 0  NOT NULL,
    shipcost     NUMERIC DEFAULT 12 NOT NULL,
    currency     TEXT    DEFAULT 'EUR',
    productcount NUMERIC DEFAULT 0  NOT NULL,
    quantity     NUMERIC DEFAULT 0  NOT NULL,
    ordered      NUMERIC DEFAULT CURRENT_TIMESTAMP,
    status       TEXT    DEFAULT 'partial',
    deleted      TEXT    DEFAULT 'n',
    changed      NUMERIC,
    created      NUMERIC DEFAULT CURRENT_TIMESTAMP
);

create table if not exists orderitems
(
    id          INTEGER PRIMARY KEY,
    cartid      TEXT,
    slug        TEXT    default '',
    details     TEXT,
    tplprev     TEXT    default '',
    tplfull     TEXT    default '',
    product_id  INTEGER               NOT NULL,
    shipaddress TEXT,
    price       NUMERIC DEFAULT 0     NOT NULL,
    shipprice   NUMERIC DEFAULT 0     NOT NULL,
    shipdetail  TEXT,
    currency    TEXT    DEFAULT 'EUR' NOT NULL,
    amount      INTEGER DEFAULT 0     NOT NULL,
    status      TEXT    DEFAULT 'processing',
    ispaid      NUMERIC DEFAULT 0     NOT NULL,
    isdelivered NUMERIC DEFAULT 0     NOT NULL,
    isreturned  NUMERIC DEFAULT 0     NOT NULL,
    isrefunded  NUMERIC DEFAULT 0     NOT NULL,
    orderid     INTEGER,
    changed     NUMERIC,
    created     NUMERIC DEFAULT CURRENT_TIMESTAMP
);

create table if not exists images
(
    id          INTEGER PRIMARY KEY,
    slug        TEXT UNIQUE,
    reldir      TEXT,
    details     TEXT,
    uid         TEXT UNIQUE,
    src         TEXT UNIQUE,
    filedate    NUMERIC,
    changed     NUMERIC,
    created     NUMERIC DEFAULT CURRENT_TIMESTAMP
);


--
create table if not exists user.cart
(
    id       INTEGER PRIMARY KEY,
    cartids  TEXT              NOT NULL,
    name     TEXT UNIQUE,
    ordernr  TEXT UNIQUE,
    isactive NUMERIC DEFAULT 0 NOT NULL, -- index makes only one can be the active cart
    changed  NUMERIC,
    created  NUMERIC DEFAULT CURRENT_TIMESTAMP
);

create table if not exists user.address
(
    id       INTEGER PRIMARY KEY,
    name     TEXT,
    street   TEXT,
    plz      TEXT,
    ort      TEXT,
    country  TEXT,
    type     TEXT    DEFAULT 'deliver', --deliver|invoice
    selected TEXT    default 'selected',
    changed  NUMERIC,
    created  NUMERIC DEFAULT CURRENT_TIMESTAMP,
    unique (name, street, plz, ort, country)
);

create table if not exists user.account
(
    id      INTEGER PRIMARY KEY,
    name    TEXT,
    email   TEXT,
    lang    TEXT,
    role    TEXT    default 'user',
    changed NUMERIC,
    created NUMERIC DEFAULT CURRENT_TIMESTAMP
);




