<?php
$text = file_get_contents('https://thestartuppitch.com/');


$linksRE = '/(?:(menu-item.*?))(?:(\shref="))(?<link>[^"]+)/';
preg_match_all($linksRE,$text,$linksMatches);
$links = array_unique($linksMatches['link']);

// $save = fopen('links.txt', 'w');
file_put_contents('links.txt', $links);
echo "<pre>";
var_dump($links);