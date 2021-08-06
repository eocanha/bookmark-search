#!/bin/bash

# Del.icio.us search script
DB=/home/enrique/delicious/delicious.sqlite

PATTERN="$1"

if [ "${PATTERN}" != "" ]
then
 sqlite3 "${DB}" '.mode lines' 'select title,url,tags,comment from links where
  tags like '\'"${PATTERN}"\'' or
  tags like '\'"${PATTERN}"',%'\'' or
  tags like '\''%,'"${PATTERN}"',%'\'' or
  tags like '\''%,'"${PATTERN}"\'';' | less
else
 read -p "url: " -e URL
 ID=$(sqlite3 "${DB}" "select id from links where url='${URL}';")

 if [ -n "${ID}" ]
 then
  TITLE=$(sqlite3 "${DB}" "select title from links where id=${ID}")
  TAGS=$(sqlite3 "${DB}" "select tags from links where id=${ID}")
  COMMENT=$(sqlite3 "${DB}" "select comment from links where id=${ID}")
 fi
 read -p "title: " -i "${TITLE}" -e TITLE
 read -p "tags: " -i "${TAGS}" -e TAGS
 read -p "comment: " -i "${COMMENT}" -e COMMENT

 if [ -n "${ID}" ]
 then
  sqlite3 "${DB}" "update links set title='${TITLE}', tags='${TAGS}', comment='${COMMENT}' where id=${ID}"
 else
  sqlite3 "${DB}" "insert into links (url,title,tags,comment) values ('${URL}','${TITLE}','${TAGS}','${COMMENT}')"
 fi
fi
