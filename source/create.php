<?php

include_once('lib/auction_creator.php');

$auction_creator						= new AuctionCreator();
$res									= $auction_creator->get_request_auth();

if($res===FALSE)
{
	echo "get_request_auth error";
	die;
}

$res									= $auction_creator->get_auth_token();
if($res===FALSE)
{
	echo "get_auth_token error";
	die;
}

$res									= $auction_creator->import_file('create.csv');
if($res===FALSE)
{
	echo "import_file error";
	die;
}

$auction_creator->post(TRUE);
$error_list								= $auction_creator->get_error_log();

if(sizeof($error_list)>0)
	print_r($error_list);




