<html>

	<html>
	<head>
		<title> Our Site </title>
	</head>
	<body>
		<center>
		<h1>TCP Dump</h1>

		<?php

		$control_ip_addr = "192.168.1.24";
		$control_user = "root";
		$control_pw = "Sickcunts3411";
		$control_ssh_port = "2222";

		$foreach_count = 0;
		$ip_netns = "";
		$selected_ip_netns = "";
		$time_diff = "";

		if (isset($_POST['port_array_hidden'])) {
			$post_int_string = $_POST['port_array_hidden'];
		$post_int_array = explode(",", $post_int_string);
		
		foreach ($post_int_array as $interface) {
			if (isset($_POST[$interface])) {
				$selected_int = $interface;
				$aaa = $foreach_count - 1;
				$prev_int = $post_int_array[$aaa];
				if (substr($prev_int,0,5) == "qrout" or substr($prev_int,0,5) == "qdhcp") {
					$ip_netns = $prev_int;
				}

			}
			$foreach_count = $foreach_count + 1;			
		}
	}

		if (empty($selected_int)) {
			$selected_int = $_POST["interface"];
			$selected_ip_netns = $_POST["ip_netns_post"];
		}

		##TRY DOING MULTIPLE PHP TAGS SO THAT THIS ISNT ONE BIG PHP BLOCK OF CODE??
		print "Selected interface to perform TCP Dump on is: <b>$selected_int</b><br>
		Please note that the packet capture will end when either of below two parameters is met:<br>
		<form action='tcpdump.php' method='post'>
		<input type='hidden' name='interface' value=$selected_int>
		<input type='hidden' name='ip_netns_post' value=$ip_netns>
		<table><tr><td>Packet Count:</td><td><input type='text' name='dump_packet' value='50'><td>   </td>
		<td>Time Taken (sec):</td><td><input type='text' name='dump_time' value='8'></td><td>   </td>
		<td><input type='submit' class='button' name='start_button' value='Start TCP Dump'></td><td>  </td>
		</tr></table><br><br>";

		###TCP DUMP OUTPUT SECTION
		print "<h3><b>Results:</b></h3>";
		print "<p align='left'>";

		if ($selected_ip_netns !== "") {
			$ip_netns_final = "ip netns exec ".$selected_ip_netns;
		}

		if (isset($_POST["start_button"])) {
			$interface1 = $_POST["interface"];

			$ssh_command_start_dump = $ip_netns_final." tcpdump -i ".$interface1." -U";
			$ssh_command_ping = $ip_netns_final." ping -I ".$interface1." 10.9.9.99";

			$connection = ssh2_connect($control_ip_addr, $control_ssh_port);
			if (!ssh2_auth_password($connection, $control_user, $control_pw)) {
				print "<script type='text/javascript'> window.onload = function () { alert('Error: SSH'); } </script>";
			}
			$connection2 = ssh2_connect($control_ip_addr, $control_ssh_port);
			if (!ssh2_auth_password($connection2, $control_user, $control_pw)) {
				print "<script type='text/javascript'> window.onload = function () { alert('Error: SSH'); } </script>";
			}
			
			$stream_dump = ssh2_exec($connection, $ssh_command_start_dump);
			$stream_ping = ssh2_exec($connection, $ssh_command_ping);
			stream_set_blocking($stream_dump, true);
			stream_set_blocking($stream_ping, true);
			$count = 0;
			$dummy_count = 0;
			$string_check = "";

			while ($line = fgets($stream_dump)) {
				if (strpos($line,"10.9.9.99")) {
					if ($dummy_count == 0) {
						$time_start = strtotime(substr($line,0,8));
						$dummy_count = $dummy_count + 1;				
					}
					$time_line = strtotime(substr($line,0,8));
					$time_diff = $time_line - $time_start;
				}
				else {
					print "$line<br>";
					$string_check = $string_check.$line;

				}

				if ($count >= $_POST["dump_packet"] or $time_diff >= $_POST["dump_time"]) {
					$ssh_command_ping_quit = "kill $(ps aux | grep 'ping' | awk '{print $2}')";
					$ssh_command_dump_quit = "kill $(ps aux | grep 'tcpdump' | awk '{print $2}')";
					$stream_ping_quit = ssh2_exec($connection, $ssh_command_ping_quit);
					$stream_dump_quit = ssh2_exec($connection, $ssh_command_dump_quit);
					if (empty($string_check)) {
						print "<script type='text/javascript'> window.onload = function () { alert('No packets were captured'); } </script>";
					}							
					break;
				}
				if (!strpos($line,"10.9.9.99")) {
					$count = $count + 1;
				}
			}
		}

		print "</p>";

		?>

		<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>

        Jonathan Chu (12214639)<br>
        Michael Gemin (12676553)<br>

    </center>

	</body>
  
  
</html>