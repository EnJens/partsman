create table parts(
   id serial,
   vendor_id varchar(255) not null unique,
   quantity int not null default 0
);

