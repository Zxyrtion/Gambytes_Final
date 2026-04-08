<?php
// Calendly API Configuration
define('CALENDLY_API_KEY', 'eyJraWQiOiIxY2UxZTEzNjE3ZGNmNzY2YjNjZWJjY2Y4ZGM1YmFmYThhNjVlNjg0MDIzZjdjMzJiZTgzNDliMjM4MDEzNWI0IiwidHlwIjoiUEFUIiwiYWxnIjoiRVMyNTYifQ.eyJpc3MiOiJodHRwczovL2F1dGguY2FsZW5kbHkuY29tIiwiaWF0IjoxNzc0MzQ1NTg2LCJqdGkiOiJjYWYxOWFkZi05NDgwLTRmOGMtYmI1My1jZWY1NjZlNzc0MjAiLCJ1c2VyX3V1aWQiOiIxYjU4OWE3NC1hMDIxLTQ4NGMtOTgyYi03OTQ4NDVlYmE0MzAiLCJzY29wZSI6ImF2YWlsYWJpbGl0eTpyZWFkIGF2YWlsYWJpbGl0eTp3cml0ZSBldmVudF90eXBlczpyZWFkIGV2ZW50X3R5cGVzOndyaXRlIGxvY2F0aW9uczpyZWFkIHJvdXRpbmdfZm9ybXM6cmVhZCBzaGFyZXM6d3JpdGUgc2NoZWR1bGVkX2V2ZW50czpyZWFkIHNjaGVkdWxlZF9ldmVudHM6d3JpdGUgc2NoZWR1bGluZ19saW5rczp3cml0ZSJ9.SWrTjsqcUYA7BdoWw07wYSRUgr41HIes3nTpuljCLP6HP80LC14N4sPPL7aKfMJoa8WWYHurOfj742CMxCZI6g');
define('CALENDLY_API_URL', 'https://api.calendly.com');
// Optional: specify a Calendly user or organization URI for event type lookup.
// If your token does not have users:read scope, set the organization URI instead.
// Use the actual API resource URI, not the public Calendly scheduling page.
define('CALENDLY_USER_URI', 'https://api.calendly.com/users/1b589a74-a021-484c-982b-794845eba430');
define('CALENDLY_ORGANIZATION_URI', '');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'gambytes');
define('DB_USER', 'root');
define('DB_PASS', '');
?>
