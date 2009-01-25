<?php
	
function humn_size ($size, $rounder = null, $min = null, $space = '&nbsp;')
{
	static $sizes   = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	static $rounders = array(0,   0,    0,    2,    3,    3,    3,    3,    3);

	$size = (float) $size;
	$ext = $sizes[0];
	$rnd = $rounders[0];

	if ($min == 'KB' && $size < 1024)
	{
		$size = $size / 1024;
		$ext  = 'KB';
		$rounder = 1;
	}
	else
	{
		for ($i=1, $cnt=count($sizes); ($i < $cnt && $size >= 1024); $i++)
		{
			$size = $size / 1024;
			$ext  = $sizes[$i];
			$rnd  = $rounders[$i];
		}
	}
	if (!$rounder)
	{
		$rounder = $rnd;
	}

	return round($size, $rounder) . $space . $ext;
}

function create_magnet($dn, $xl = false, $btih = '', $tr = '')
{
	$magnet = 'magnet:?';
	if ($dn){
		$magnet .= 'dn=' . $dn; // download name
	}
	if ($xl){
		$magnet .= '&xl=' . $xl; // size
	}
	if ($btih){
		$magnet .= '&xt=urn:btih:' . $btih; // bittorrent info_hash (Base32)
	}
	if ($tr){
		$magnet .= '&tr=' . $tr; // gnutella sha1 (base32)
	}
	return $magnet;
}

function base32_encode ($inString) 
{ 
    $outString = ""; 
    $compBits = ""; 
    $BASE32_TABLE = array( 
                          '00000' => 0x61, 
                          '00001' => 0x62, 
                          '00010' => 0x63, 
                          '00011' => 0x64, 
                          '00100' => 0x65, 
                          '00101' => 0x66, 
                          '00110' => 0x67, 
                          '00111' => 0x68, 
                          '01000' => 0x69, 
                          '01001' => 0x6a, 
                          '01010' => 0x6b, 
                          '01011' => 0x6c, 
                          '01100' => 0x6d, 
                          '01101' => 0x6e, 
                          '01110' => 0x6f, 
                          '01111' => 0x70, 
                          '10000' => 0x71, 
                          '10001' => 0x72, 
                          '10010' => 0x73, 
                          '10011' => 0x74, 
                          '10100' => 0x75, 
                          '10101' => 0x76, 
                          '10110' => 0x77, 
                          '10111' => 0x78, 
                          '11000' => 0x79, 
                          '11001' => 0x7a, 
                          '11010' => 0x32, 
                          '11011' => 0x33, 
                          '11100' => 0x34, 
                          '11101' => 0x35, 
                          '11110' => 0x36, 
                          '11111' => 0x37, 
                          ); 
    
    /* Turn the compressed string into a string that represents the bits as 0 and 1. */
    for ($i = 0; $i < strlen($inString); $i++) {
        $compBits .= str_pad(decbin(ord(substr($inString,$i,1))), 8, '0', STR_PAD_LEFT);
    }
    
    /* Pad the value with enough 0's to make it a multiple of 5 */
    if((strlen($compBits) % 5) != 0) {
        $compBits = str_pad($compBits, strlen($compBits)+(5-(strlen($compBits)%5)), '0', STR_PAD_RIGHT);
    }
    
    /* Create an array by chunking it every 5 chars */
    $fiveBitsArray = split("\n",rtrim(chunk_split($compBits, 5, "\n"))); 
    
    /* Look-up each chunk and add it to $outstring */
    foreach($fiveBitsArray as $fiveBitsString) { 
        $outString .= chr($BASE32_TABLE[$fiveBitsString]); 
    } 
    
    return $outString; 
}

function hex2bin($h)
{
  if (!is_string($h)) return null;
  $r='';
  for ($a=0; $a<strlen($h); $a+=2) { $r.=chr(hexdec($h{$a}.$h{($a+1)})); }
  return $r;
}