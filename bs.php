<?php

function url(){
  if(isset($_SERVER['HTTPS']))
    $protocol = ($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https" : "http";
  else
    $protocol = 'http';
  return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

?><html>
<head>
 <script>
 function createBookmarkHowto() {
   prompt("Use this bookmarklet to create bookmarks:",
     "javascript:location.href='<?= url() ?>"
     + "?url='+encodeURIComponent(window.location.href)"
     + "+'&title='+encodeURIComponent(document.title)");
 }

 function confirmDeletion(actionUrl) {
   if (confirm("Are you sure you want to delete this bookmark?")) {
     window.location=actionUrl;
   }
 }

 function copyTags(tagsSpanId) {
   var tagsSpan = document.getElementById(tagsSpanId);
   if (tagsSpan) {
     var textArea = document.createElement("textarea");
     textArea.value = tagsSpan.textContent;
     document.body.appendChild(textArea);
     textArea.select();
     textArea.setSelectionRange(0, 99999); // For mobile devices
     document.execCommand("copy");
     textArea.remove();
   }
 }
 </script>

 <style>
  body, td {
   font-family: Arial, Helvetica, sans-serif;
   font-size: 14px;
  }

  .main {
   margin: auto;
   width: fit-content;
  }

  .searchbox {
  }

  .match {
  }

  .match td {
   vertical-align: top;
   padding-bottom: 10px;
  }

  .match .link {
   text-decoration: none;
   color: #039;
  }

  .match .site {
   color: #093;
  }

  .match .tags {
   text-decoration: none;
   color: #033;
  }

  .match .comment {
   color: #666;
  }

  .match .date {
   color: #990;
   font-style: italic;
  }

  .match .actions {
   text-decoration: none;
   color: #900;
  }

  .edit-title {
   font-size: 18px;
   padding-bottom: 10px;
  }

  .edit-field {
   width: 400px;
  }

 </style>
 <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<pre>
<?php
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

$db=new SQLite3(dirname(__FILE__) . "/bookmarks.sqlite");

// Create empty DB if needed
$statement = $db->prepare('
 create table if not exists links (
  id integer primary key,
  title text,
  url text,
  tags text,
  comment text,
  add_date integer
 );
');
$result = $statement->execute();
$result->finalize();

foreach (["pattern", "action", "id", "title", "url", "tags", "comment", "save"] as $parameter) {
  ${$parameter} = (isset($_GET[$parameter])) ? $_GET[$parameter] : null;
}

if ($action == "delete" && !empty($id)) {
  $statement = $db->prepare('
    delete from links
    where
      id = :id;');
  $statement->bindValue(':id', $id);
  $result = $statement->execute();
  $result->finalize();
  $id = null;
}

if (!is_null($pattern)) {
  $statement = $db->prepare('
    select id,title,url,tags,datetime(add_date, \'unixepoch\') as date,comment
    from links ' . (!empty($pattern) ? '
    where
      tags like :pattern1 or
      tags like :pattern2 or
      tags like :pattern3 or
      tags like :pattern4
    ' : '') . '
    order by date desc;');
  if (!empty($pattern)) {
    $statement->bindValue(':pattern1', $pattern);
    $statement->bindValue(':pattern2', $pattern . ',%');
    $statement->bindValue(':pattern3', '%,' . $pattern);
    $statement->bindValue(':pattern4', '%,' . $pattern . ',%');
  }
  $result = $statement->execute();
  $matches = [];
  while ($result->numColumns() > 0 && $row = $result->fetchArray(SQLITE3_ASSOC))
    $matches[] = $row;
  $result->finalize();
}

if (!empty($save)) {
  $tags = preg_replace('/ *, */', ',', strtolower($tags));
  if (!empty($id)) {
    $statement = $db->prepare('
      update links set
      title=:title, url=:url, tags=:tags, add_date=strftime(\'%s\', \'now\'), comment=:comment
      where
        id = :id;');
  } else {
    $statement = $db->prepare('
      insert into links (title,url,tags,add_date,comment)
      values (:title, :url, :tags, strftime(\'%s\', \'now\'), :comment);');
  }
  $statement->bindValue(':title', $title);
  $statement->bindValue(':url', $url);
  $statement->bindValue(':tags', $tags);
  $statement->bindValue(':comment', $comment);
  if (!empty($id))
    $statement->bindValue(':id', $id);
  $result = $statement->execute();
  $result->finalize();
}

if (!empty($id) || !empty($url)) {
  $statement = $db->prepare('
    select id,title,url,tags,datetime(add_date, \'unixepoch\') as date,comment
    from links
    where ' . (!empty($id) ? "id = :id" : "url = :url") . ';');
  if (!empty($id)) {
    $statement->bindValue(':id', $id);
  } else {
    $statement->bindValue(':url', $url);
  }
  $result = $statement->execute();
  $record = [
    "id" => null,
    "title" => $title,
    "url" => $url,
    "tags" => $tags,
    "date" => "Never",
    "comment" => $comment
  ];
  while ($result->numColumns() > 0 && $row = $result->fetchArray(SQLITE3_ASSOC)) {
    $record = $row;
    break;
  }
  $result->finalize();
}

$db->close();
?>
</pre>

<div class="main">

<?php
if (empty($record)) {
?>
  <div style="text-align: center;">
    <div class="edit-title">
      &#11088; Bookmark search by tag
    </div>

    <form>
      <input class="searchbox" type="text" name="pattern" value="<?= $pattern ?>" autofocus />
      <input type="submit" value="&#x1f50D;"/>
      <input type="button" value="&#x1f516;" onclick="javascript:createBookmarkHowto();"/>
    </form>
  </div>
<?php
}

if (!empty($matches)) {
?>
  <table class="match">
    <tr>
     <td></td>
     <td>
      <?= count($matches) ?> results
     </td>
    </tr>
<?php
  foreach ($matches as $row) {
?>
    <tr>
      <td style="text-align: right;">&#11088;</td>
      <td>
       <span class="link"><a class="link" href="<?= $row["url"] ?>"><?= htmlspecialchars($row["title"]) ?></a></span>
       &nbsp;-&nbsp;
       <span class="site"><?= htmlspecialchars(parse_url($row["url"], PHP_URL_HOST)) ?></span>
       &nbsp;-&nbsp;
       <span class="date"><?= $row["date"] ?></span>
       <br/>
       <span class="tags">[<span id="<?= "tags-" . $row["id"] ?>"><?php
    foreach (explode(",", $row["tags"]) as $i => $tag) {
       ?><?= $i ? ", " : ""?><a class="tags" href="?pattern=<?= urlencode($tag) ?>"><?= htmlspecialchars($tag) ?></a><?php
    }
       ?></span>]</span>
       &nbsp;-&nbsp;
       <span class="actions">
        <a class="actions" href="?action=edit&id=<?= $row["id"] ?>">Edit</a> ·
        <a class="actions" href="#"
         onclick="javascript:confirmDeletion('?action=delete&id=<?= $row["id"] ?>')">Delete</a> ·
        <a class="actions" href="#"
         onclick="javascript:copyTags('<?= "tags-" . $row["id"] ?>')">Copy tags</a>
       </span>

<?php
    if (!empty($row["comment"])) {
       ?>
       <br/>
       <span class="comment">
         <?= !empty($row["comment"]) ? htmlspecialchars($row["comment"]) : ""?>
       </span>
<?php
    }
?>
      </td>
    </tr>
<?php
  }
?>
  </table>
<?php
}

if (!empty($record) && $action != "delete") {
?>
  <form>
    <input type="hidden" name="id" value="<?= $record["id"] ?>"/>
    <table>
      <tr>
        <td>&nbsp;</td>
        <td>
          <div class="edit-title">
            &#11088; <?= !empty($record["id"]) ? "Edit" : "Add" ?> bookmark
          </div>
        </td>
      </tr>
      <tr>
        <td>Name:</td>
        <td><input class="edit-field" type="text" name="title"
             value="<?= htmlspecialchars($record["title"]) ?>"/></td>
      </tr>
      <tr>
        <td>Link (URL):</td>
        <td><input class="edit-field" type="text" name="url"
             value="<?= htmlspecialchars($record["url"]) ?>"/></td>
      </tr>
      <tr>
        <td>Tags:</td>
        <td><input class="edit-field" type="text" name="tags" autofocus
             value="<?= htmlspecialchars($record["tags"]) ?>"/></td>
      </tr>
      <tr>
        <td style="vertical-align: top; padding-top: 3px;">Notes:</td>
        <td><textarea class="edit-field" rows="4" name="comment"><?=
          htmlspecialchars($record["comment"])
        ?></textarea></td>
      </tr>
      <tr>
        <td>Last saved:</td>
        <td><?= $record["date"] ?></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td>
          <br/>
          <input type="submit" name="save" value="Save"/>
          <input type="button" value="Return to page"
           onclick="javascript:location.replace('<?= htmlspecialchars($record["url"]) ?>');"/>
          <input type="button" value="Go to bookmark search list"
           onclick="javascript:window.location='?'"/>
        </td>
      </tr>
    </table>
  </form>
<?php
}
?>
</div>

</body>
</html>
