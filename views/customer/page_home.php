<?
if(!Session::IsCustomer()) Redirect();
if(empty(Session::SelectedTable()) && Session::CustomerId()<=0) { Session::Remove('customer'); Redirect(); }
include 'page_menu.php';
