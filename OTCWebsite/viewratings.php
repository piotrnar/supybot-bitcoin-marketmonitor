<!DOCTYPE html>
<?php
 $pagetitle = "#bitcoin-otc web of trust data";
 include("header.php");
?>

<?php
	$page_len = 100;

	$validkeys = array('id', 'nick', 'created_at', 'keyid', 'total_rating', 'pos_rating_recv_count', 'neg_rating_recv_count', 'pos_rating_sent_count', 'neg_rating_sent_count');
	define('DEFAULT_SORT_IDX', 4); // total_rating

	if (isset($_GET['sortby'])) {
		$sortbyidx = intval($_GET['sortby']);
		if ($sortbyidx<0)  $sortbyidx=DEFAULT_SORT_IDX;
		elseif  ($sortbyidx>=count($validkeys))  $sortbyidx=DEFAULT_SORT_IDX;
	} else $sortbyidx = DEFAULT_SORT_IDX;

	$sortby = $validkeys[$sortbyidx];

	$sortasc = intval(isset($_GET['sortasc']) && intval($_GET['sortasc']));
?>

<div class="breadcrumbs">
<a href="/">Home</a> &rsaquo;
<a href="trust.php">Web of Trust</a> &rsaquo;
Web of Trust Data
</div>

  <h3>Summary statistics on web of trust</h3>
  <ul><?php
	try { $db = new PDO('sqlite:./otc/RatingSystem.db'); }
	catch (PDOException $e) { die($e->getMessage()); }
	if (!$query = $db->Query('SELECT count(*) as usercount, sum(total_rating) as ratingsum FROM users'))
		echo "<li>No outstanding orders found</li>\n";
	else {
		$entry = $query->fetch(PDO::FETCH_BOTH);
		echo "<li>" . $entry['usercount'] . " users in database, with a total of " . $entry['ratingsum'] . " net rating points.</li>\n";
	}

	if (!$query = $db->Query("SELECT count(*) as ratingcount, sum(rating) as ratingsum FROM ratings WHERE rating > 0"))
		echo "<li>No positive ratings found</li>\n";
	else {
		$entry = $query->fetch(PDO::FETCH_BOTH);
		echo "<li>" . $entry['ratingcount'] . " positive ratings sent, for a total of " . $entry['ratingsum'] . " points.</li>\n";
	}

	if (!$query = $db->Query("SELECT count(*) as ratingcount, sum(rating) as ratingsum FROM ratings WHERE rating < 0"))
		echo "<li>No negative ratings found</li>\n";
	else {
		$entry = $query->fetch(PDO::FETCH_BOTH);
		echo "<li>" . $entry['ratingcount'] . " negative ratings sent, for a total of " . $entry['ratingsum'] . " points.</li>\n";
	}

	$db->Query("attach database './otc/GPG.db' as gpg");

	$page_idx = intval($_GET['page']);
	if ($query = $db->Query('SELECT count(*) as rowcnt from main.users rsusers, gpg.users gpg WHERE gpg.nick = rsusers.nick')) {
		$entry = $query->fetch(PDO::FETCH_BOTH);
		$page_cnt = intval(($entry['rowcnt']+$page_len-1)/$page_len);
	} else {
		$page_cnt = 1;
	}
	if ($page_idx>=$page_cnt) $page_idx=$page_cnt-1;
	elseif ($page_idx<0) $page_idx=0;
?>
  </ul>

<table class="datadisplay" style="width: 100%;">
<tr>
 <td>
  <h3>List of users and ratings <?php
  echo ' - page #'.($page_idx+1).' / '.$page_cnt;
  echo ' - ';
  $extrurl = "&sortby=$sortbyidx&sortasc=$sortasc";
  if ($page_idx>0) {
  	echo '<a href="?page='.($page_idx-1).$extrurl.'">Prev page</a>';
  }
  if ($page_idx>0 && $page_idx+1<$page_cnt) {
  	echo '&nbsp;|&nbsp;';
  }
  if ($page_idx+1<$page_cnt) {
  	echo '<a href="?page='.($page_idx+1).$extrurl.'">Next page</a>';
  }
?></h3>
</td>
<td style="text-align: right;">
<form method="GET" action="ratingsfilter.php?">
<label>Search notes: <input type="text" name="notes" value=""/></label>
<input type="submit" value="Filter" />
</form>
</td>
</tr>
</table>

   <table class="datadisplay">
   <tr>
<?php
	foreach ($validkeys as $key) $colheaders[$key] = array('linktext' => str_replace("_", " ", $key));
	$colheaders["created_at"]["linktext"] = "first rated";
	$colheaders["created_at"]["othertext"] = "(UTC)";
	$colheaders["pos_rating_recv_count"]["linktext"] = "number of positive ratings received";
	$colheaders["neg_rating_recv_count"]["linktext"] = "number of negative ratings received";
	$colheaders["pos_rating_sent_count"]["linktext"] = "number of positive ratings sent";
	$colheaders["neg_rating_sent_count"]["linktext"] = "number of negative ratings sent";
	$sidx = 0;
	foreach ($colheaders as $by => $colhdr) {
		if ($sortbyidx==$sidx) {
			$extr = $sortasc ? '&nbsp;&#x25B4;' : '&nbsp;&#x25BE;';
			$sasc = !$sortasc;
		} else {
			$extr = '';
			$sasc = 0;
		}
		echo "    <th><a href=\"?page=0&sortby=$sidx&sortasc=$sasc\">" . $colhdr["linktext"] . (!empty($colhdr["othertext"]) ? "<br>".$colhdr["othertext"] : "").$extr."</a></th>\n";
		$sidx++;
	}
?>
   </tr>
<?php
	if (!$query = $db->Query('SELECT rsusers.*, gpg.keyid FROM main.users rsusers, gpg.users gpg
		WHERE gpg.nick = rsusers.nick
		ORDER BY ' . $sortby . ' COLLATE NOCASE ' . ($sortasc ? "ASC" : "DESC") .
		' LIMIT ' . ($page_idx*$page_len). ', '.$page_len)) {
		echo "<tr><td>No users found</td></tr>\n";
	} else {
		//$resultrow = 0;
		//$results = $query->fetchAll(PDO::FETCH_BOTH);
		$color = 0;
		while ($entry = $query->fetch(PDO::FETCH_BOTH)) {
			if ($color++ % 2) $class="even"; else $class="odd";
?>
   <tr class="<?php echo $class; ?>">
    <td><?php echo $entry['id']; ?></td>
    <td><a href="viewratingdetail.php?nick=<?php echo htmlentities($entry['nick']); ?>&sign=ANY&type=RECV"><?php echo htmlentities($entry['nick']); ?></a></td>
    <td class="nowrap"><?php echo gmdate('Y-m-d H:i:s', $entry['created_at']); ?></td>
	<td><a href="viewgpg.php?nick=<?php echo htmlentities($entry['nick']); ?>"><?php echo $entry['keyid']; ?></a></td>
    <td><?php echo $entry['total_rating']; ?></td>
    <td><a href="viewratingdetail.php?nick=<?php echo htmlentities($entry['nick']); ?>&sign=POS&type=RECV"><?php echo $entry['pos_rating_recv_count']; ?></a></td>
    <td><a href="viewratingdetail.php?nick=<?php echo htmlentities($entry['nick']); ?>&sign=NEG&type=RECV"><?php echo $entry['neg_rating_recv_count']; ?></a></td>
    <td><a href="viewratingdetail.php?nick=<?php echo htmlentities($entry['nick']); ?>&sign=POS&type=SENT"><?php echo $entry['pos_rating_sent_count']; ?></a></td>
    <td><a href="viewratingdetail.php?nick=<?php echo htmlentities($entry['nick']); ?>&sign=NEG&type=SENT"><?php echo $entry['neg_rating_sent_count']; ?></a></td>
   </tr>
<?
		}
	}
?>
  </table>

<?php
 include("footer.php");
?>

 </body>
</html>