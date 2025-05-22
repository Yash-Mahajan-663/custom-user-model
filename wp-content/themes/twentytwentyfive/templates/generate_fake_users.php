<?php
// generate_fake_users.php
// This script generates a large CSV file with fake user data for WordPress import testing.

$num_users = 1000; // Generate 100,000 fake users
$filename = 'fake_users_100k.csv';
$filepath = __DIR__ . '/' . $filename; // CSV file will be saved in the same directory as this script

// Open the CSV file for writing
$file = fopen($filepath, 'w');

if ($file === false) {
	die("Error: Could not open file for writing: " . $filepath . "\n");
}

// Define CSV header row (these are common WordPress user fields you can map)
$header = array(
	'user_login',    // Required: Unique username
	'user_email',    // Required: Unique email address
	'user_pass',     // Optional: If empty, WordPress generates a password
	'first_name',    // Optional
	'last_name',     // Optional
	'user_url',      // Optional
	'description',   // Optional
	'role'           // Optional: e.g., subscriber, editor, author. If empty, default role is used.
);
fputcsv($file, $header); // Write the header row to the CSV file

echo "Generating {$num_users} fake users into '{$filename}'...\n";

// Loop to generate user data
for ($i = 1; $i <= $num_users; $i++) {
	$username = 'testuser_' . $i;
	$email = 'testuser@example.com'; // Use example.com for fake emails
	$password = 'password' . rand(100, 999); // Simple fake password
	$first_name = 'Fake';
	$last_name = 'User' . $i;
	$user_url = 'https://fake-site.com/user/' . $i;
	$description = 'This is a fake test user generated for import functionality testing. ID: ' . $i . '.';
	$role = 'subscriber'; // All will be subscribers for simplicity

	// Add a few users with different roles for varied testing
	if ($i % 10000 === 0) { // Every 10,000th user
		if ($i % 20000 === 0) {
			$role = 'editor';
		} else {
			$role = 'author';
		}
	}

	$row_data = array(
		$username,
		$email,
		$password,
		$first_name,
		$last_name,
		$user_url,
		$description,
		$role,
	);
	fputcsv($file, $row_data); // Write the current row to the CSV file

	if ($i % 10000 === 0) { // Print progress every 10,000 users
		echo "  Generated " . $i . " users...\n";
	}
}

fclose($file); // Close the CSV file
echo "\nCSV file '{$filename}' with {$num_users} fake users generated successfully at: " . $filepath . "\n";
