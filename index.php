<?php

$debug = false;

if($debug) {
	error_reporting(E_ALL);
	ini_set('display_errors', true);
}

function collectPosts($posts) {
	$read = 0;
	$completePosts = array();
	$header = null;	
	$post = null;
	foreach( $posts->childNodes as $i => $child ) {
		if($read === 0) {					
			$header = $child;
		}
		elseif( $read === 1 ) {
		}
		elseif ( $read === 2 ) {
			$post = $child;
		}
		elseif ( $read === 3 ) {		
			$completePosts[] = array( $header, $post );
			$read = -1;
		}
		$read++;
	}	
	return $completePosts;
}

function parseInfo( $post, $ignoreQuotes = true, $deadline ) {
	$info = array();	
	$info['time'] = extractDate( $post[0]->childNodes->item(6)->textContent );
	$info['daynum'] = getGameDay($post[0]->childNodes->item(6)->textContent, $deadline );
	$info['poster'] = $post[0]->childNodes->item(4)->textContent;
	$info['content'] = getContent($post[1], $ignoreQuotes);
	
	return $info;
}

function getContent(DOMNode $node, $ignoreQuotes = true) {
	if($ignoreQuotes) {
		recursiveStripQuotes($node);
	}
	return $node->textContent;
}

function recursiveStripQuotes(DOMNode $node) {
	if(!$node->childNodes) {
		return;
	}
	$purge = array();
	foreach($node->childNodes as $child) {
			
		$class = null;		
		if($child->attributes) {
			$class = $child->attributes->getNamedItem('class');
		}
		
		if($class && $class->value == 'quoteheader') {
			$purge[] = $child;
		}
		elseif($class && $class->value == 'quotefooter') {
			$purge[] = $child;
		}
		elseif($child->nodeName == 'blockquote') {
			$purge[] = $child;
		}
		else {
			recursiveStripQuotes($child);
		}
	}
	foreach($purge as $child) {
		$node->removeChild($child);
	}
	return $node;
}

function extractDate( $time ) {
	return substr( $time, 0, strpos( $time, "," ) );
}

function getGameDay( $time, $deadline ) {
	
	$parts = explode(" ", $time );
	
	$dayNum = $parts[0];
	$timeOfDay = str_replace( ":" , "", $parts[4] );
	if( (int) $timeOfDay > (int) $deadline ) {
		$dayNum += 1;
	}
	return $dayNum . " " . $parts[1];
}

function buildData( $parsed ) {
	$out = array();
	$playerData = array();
	foreach( findAllPlayers( $parsed ) as $player ) {
		$playerData[$player] = 0;
	}
	foreach( $parsed as $item ) {
		if(!isset($out[$item['daynum']] )) $out[$item['daynum']] = $playerData;		
		$out[ $item['daynum'] ][ $item['poster'] ] += str_word_count( $item['content'] );
	}
	return $out;
}

function findAllPlayers( $parsed ) {
	$players = array();
	foreach( $parsed as $item ) {
		if( !in_array( $item['poster'], $players ) ) {
			$players[] = $item['poster'];
		}
	}
	sort( $players );
	return $players;
}

function prettyOutput( $data, $wordsNeeded ) {	
	foreach( $data as $time => $info ) {
		$totalPosted = 0;
		$enoughPosted = 0;
		foreach($info as $count) {
			if($count>0) {
				$totalPosted++;				
			}
			if($count>$wordsNeeded) {
				$enoughPosted++;
			}
		}
	
		echo '<b>' . $time . ' (' . $totalPosted . ' posters; ' . $enoughPosted . ' met genoeg woorden) </b>';
		echo '<ul>';
		foreach( $info as $player => $words ) {
			echo '<li>' . $player . ' schreef ' . prettyWordcount( $words, $wordsNeeded ) . ' woorden </li>';
		}
		echo '</ul>';
	}
}

function prettyWordcount( $count, $wordsNeeded ) {
	if( $count >= $wordsNeeded ) {
		$color = 'green';
	} 
	elseif( $count > 0 ) {
		$color = 'orange';
	}
	else {
		$color = 'red';
	}
	return '<span style="color: ' . $color . '">' . $count . '</span>';
}

if( $_SERVER['REQUEST_METHOD'] === 'POST' ) {

	$source = $_POST['source']; 	
	$altSource = $_POST['altsource'];
	$wordsNeeded = $_POST['words'];
	$deadline = str_replace( ":", "", $_POST['time'] );
	if($source) {		
		$doc = DOMDocument::loadHTMLFile ($source);
	}
	else {
		$doc = DOMDocument::loadHtml( $altSource );		
	}
	$ignoreQuotes = isset( $_POST['ignore_quotes'] ) && $_POST['ignore_quotes'];

	$posts = $doc->getElementById ( "posts" );
	$completePosts = collectPosts( $posts );

	if($ignoreQuotes) {
		echo 'Quotes worden <font style="color: red">NIET</font> geteld.<br />';
	}
	else {
		echo 'Quotes worden <font style="color: green">WEL</font> geteld.<br />';
	}
	echo '<br />';
	
	$parsed = array();
	foreach( $completePosts as $post ) {
		$parsed[] = parseInfo( $post,$ignoreQuotes, $deadline );
	}

	prettyOutput( buildData( $parsed ), $wordsNeeded );

}
else {
	echo 'plak hier de "print" url van je speculatie topic:<br />';
	echo '<form method="post" action="">';
	echo '<input type="text" name="source" /><br />';
	echo 'Of plak hier de source (control+U als de pagina open is, alles kopieren+plakken) van de print-url je speculatie topic, als die niet publiekelijk toegankelijk is:<br />';
	echo '<textarea name="altsource" style="width: 100%; height: 500px;"></textarea><br />';
	echo 'geef hier op hoeveel woorden per dag nodig zijn:<br />';
	echo '<input type="text" name="words" /><br />';
	echo '<input type="checkbox" name="ignore_quotes" id="ignore_quotes" /><label for="ignore_quotes">Negeer quotes bij tellen</label><br />';
	echo 'geef hier aan hoe laat je deadline is<br />';
	echo '<input type="text" name="time" value="22:00" /><br />';
	echo '<input type="submit" value="analyseer" />';
	echo '</form>';
}