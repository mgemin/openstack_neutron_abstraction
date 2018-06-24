<html>

	<html>
	<head>
		<title> Our Site </title>
	</head>
	<body>
		<center>
		<h1>Main Page</h1>

		<form action="tcpdump.php" method="post" target="_blank">
		<input type="submit" name="submit" value="Perform TCP Dump"> Note that this will open a new window<br><br>

		<?php

		## TOY WITH THE IDEA OF BEING ABLE TO CHANGE HOW THEY ARE SORTED? I.E. BY TENANT, BY NETWORK etc etc etc

		$control_ip_addr = "192.168.1.24";
		$control_user = "root";
		$control_pw = "Sickcunts3411";
		$control_ssh_port = "2222";
		$control_db_user = "root";
		$control_db_pw = "root";

		#Keystone - project/tenant query
		$keystone_project = mysqli_connect($control_ip_addr, $control_db_user, $control_db_pw, "keystone");
		if (!$keystone_project)
			die("Could not connect to server");
		#mysqli_select_db($keystone_project,"keystone");
		$result1 = mysqli_query($keystone_project,"select id,name from project;");
		$num_rows_result1 = mysqli_num_rows($result1);

		#Neutron - networks query
		$neutron_networks = mysqli_connect($control_ip_addr, $control_db_user, $control_db_pw);
		if (!$neutron_networks)
			die("Could not connect to server");
		mysqli_select_db($neutron_networks,"neutron");
		$result2 = mysqli_query($neutron_networks,"select name,id,project_id from networks;");

		#Neutron - subnets/cidr query
		$neutron_subnets = mysqli_connect($control_ip_addr, $control_db_user, $control_db_pw);
		if (!$neutron_subnets)
			die("Could not connect to server");
		mysqli_select_db($neutron_networks,"neutron");
		$result3 = mysqli_query($neutron_networks,"select network_id,cidr from subnets;");

		#Neutron - ports query
		$neutron_ports_ip = mysqli_connect($control_ip_addr, $control_db_user, $control_db_pw);
		if (!$neutron_ports_ip)
			die("Could not connect to server");
		mysqli_select_db($neutron_ports_ip,"neutron");
		$result4 = mysqli_query($neutron_ports_ip,"select ports.id,ports.network_id,ports.device_owner,ports.device_id, ipallocations.ip_address from ports inner join ipallocations on ports.id=ipallocations.port_id");

		#Neutron - routers query
		$neutron_routers = mysqli_connect($control_ip_addr, $control_db_user, $control_db_pw);
		if (!$neutron_routers)
			die("Could not connect to server");
		mysqli_select_db($neutron_routers,"neutron");
		$result5 = mysqli_query($neutron_routers,"select id,name from routers;");

		#Nova - instances query
		$nova_instances = mysqli_connect($control_ip_addr, $control_db_user, $control_db_pw);
		if (!$nova_instances)
			die("Could not connect to server");
		mysqli_select_db($nova_instances,"nova");
		$result6 = mysqli_query($nova_instances,"select uuid,display_name from instances;");


		function SSH_func($ssh_command) {
			
			global $control_ip_addr,$control_user,$control_pw,$control_ssh_port;

			$connection = ssh2_connect($control_ip_addr, $control_ssh_port);
			if (!ssh2_auth_password($connection, $control_user, $control_pw)) {
				print "<script type='text/javascript'> window.onload = function () { alert('Error: SSH'); } </script>";
			}
			$stream = ssh2_exec($connection, $ssh_command);
			stream_set_blocking($stream, true);
			$stream_output = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
			$ssh_output = stream_get_contents($stream_output);
			return $ssh_output;
		}

		$ssh_output1 = SSH_func('brctl show');
		$ssh_output2 = SSH_func('ovs-vsctl show');

		function Get_first_11_char($string) {
			return substr($string, 0, 11);
		}

		$error_msg = "N/A - Error";
		$checkbox_carry = array();

		if ($num_rows_result1 > 0) {
			print "<table border='1'><tr><td><b>Project Name:</b></td><td><b>Network Name:</b></td><td><b>Network Address/Prefix:</b></td><td><b>Interfaces Type:</b></td><td><b>Name:</b></td><td><b>IP Address:</b></td><td><b>OpenStack Port ID:</b></td><td><b>Port Type (or system that its connected to):</b></td><td><b>Linux Device Name:</b></td><td><b>TCP Dump:</b></td><td><b>Comments:</b></tr>";

			while ($row_results1 = mysqli_fetch_array($result1,MYSQLI_NUM)) {
				if ($row_results1[1] !== "<<keystone.domain.root>>") {
					while ($row_results2 = mysqli_fetch_array($result2,MYSQLI_NUM)) {
						if ($row_results2[2] == $row_results1[0]) {
							while ($row_results3 = mysqli_fetch_array($result3,MYSQLI_NUM)) {
								if ($row_results3[0] == $row_results2[1]) {
									while ($row_results4 = mysqli_fetch_array($result4,MYSQLI_NUM)) {
										if ($row_results4[1] == $row_results2[1]) {
											if (strpos($row_results4[2],'nova') == true) {
												print "<tr><td>$row_results1[1]</td><td>$row_results2[0]</td><td>$row_results3[1]</td><td>Instance</td>";
												while ($row_results6 = mysqli_fetch_array($result6,MYSQLI_NUM)) {
													if ($row_results6[0] == $row_results4[3]) {
														print "<td>$row_results6[1]</td><td>$row_results4[4]</td><td>$row_results4[0]</td>";
														$append_char_str = Get_first_11_char($row_results4[0]);
														$lb_tap_string = 'tap'.$append_char_str;												
														$qbr_string = 'qbr'.$append_char_str;
														$qvb_string = 'qvb'.$append_char_str;
														$qvo_string = 'qvo'.$append_char_str;												
														if (strpos($ssh_output1, $lb_tap_string) == true) {
															print "<td>Linux Bridge</td><td>$lb_tap_string</td><td><input type='checkbox' name=$lb_tap_string></td></tr>";
															array_push($checkbox_carry,$lb_tap_string);
														}
														else {
															print "<td>$error_msg</td><td>$error_msg</td><td></td><td>It appears that the Tap interface for Instance: $row_results6[1] does not exist, or the instance is not powered on</td></tr>";
														}
														if (strpos($ssh_output1, $qbr_string) == true) {
															print "<tr><td>$row_results1[1]</td><td>$row_results2[0]</td><td>$row_results3[1]</td><td>Instance</td><td>$row_results6[1]</td><td>$row_results4[4]</td><td>$row_results4[0]</td><td>Linux Bridge</td><td>$qbr_string</td><td><input type='checkbox' name=$qbr_string></td></tr>";
															array_push($checkbox_carry,$qbr_string);
														}
														else {
															print "<tr><td>$row_results1[1]</td><td>$row_results2[0]</td><td>$row_results3[1]</td><td>Instance</td><td>$row_results6[1]</td><td>$row_results4[4]</td><td>$row_results4[0]</td><td>$error_msg</td><td>$error_msg</td><td></td><td>It appears that the Linux Bridge for Instance: $row_results6[1] does not exist</td></tr>";
														}
														if (strpos($ssh_output1, $qvb_string) == true) {
															print "<tr><td>$row_results1[1]</td><td>$row_results2[0]</td><td>$row_results3[1]</td><td>Instance</td><td>$row_results6[1]</td><td>$row_results4[4]</td><td>$row_results4[0]</td><td>Linux Bridge</td><td>$qvb_string</td><td><input type='checkbox' name=$qvb_string></td></tr>";
															array_push($checkbox_carry,$qvb_string);
														}
														else {
															print "<tr><td>$row_results1[1]</td><t>$row_results2[0]</td><td>$row_results3[1]</td><td>Instance</td><td>$row_results6[1]</td><td>$row_results4[4]</td><td>$row_results4[0]</td><td>$error_msg</td><td>$error_msg</td><td></td><td>It appears that the qvb-vEth for Instance: $row_results6[1] does not exist</td></tr>";
														}
														if (strpos($ssh_output2, $qvo_string) == true) {
															print "<tr><td>$row_results1[1]</td><td>$row_results2[0]</td><td>$row_results3[1]</td><td>Instance</td><td>$row_results6[1]</td><td>$row_results4[4]</td><td>$row_results4[0]</td><td>Open vSwitch</td><td>$qvo_string</td><td><input type='checkbox' name=$qvo_string></td></tr>";
															array_push($checkbox_carry,$qvo_string);
														}
														else {
															print "<tr><td>$row_results1[1]</td><td>$row_results2[0]</td><td>$row_results3[1]</td><td>Instance</td><td>$row_results6[1]</td><td>$row_results4[4]</td><td>$row_results4[0]</td><td>$error_msg</td><td>$error_msg</td><td></td><td>It appears that the qvo-vEth for Instance: $row_results6[1] does not exist</td></tr>";												
														}
													}	
												}
												mysqli_data_seek($result6, 0);
											}

											elseif (strpos($row_results4[2],'router') == true) {
												print "<tr><td>$row_results1[1]</td><td>$row_results2[0]</td><td>$row_results3[1]</td><td>Router</td>";
												while ($row_results5 = mysqli_fetch_array($result5,MYSQLI_NUM)) {
													if ($row_results5[0] == $row_results4[3]) {
														print "<td>$row_results5[1]</td><td>$row_results4[4]</td><td>$row_results4[0]</td>";
														$ssh_router_command = 'ip netns exec qrouter-'.$row_results5[0].' ip a';
														$ssh_output3 = SSH_func($ssh_router_command);														
														$append_char_str = Get_first_11_char($row_results4[0]);
														$qg_string = 'qg-'.$append_char_str;
														$qr_string = 'qr-'.$append_char_str;
														array_push($checkbox_carry,"qrouter-".$row_results5[0]);
														if (strpos($ssh_output3,$qg_string) == true) {
															print "<td>Linux Namespace</td><td>$qg_string</td><td><input type='checkbox' name=$qg_string></td></tr>";
															array_push($checkbox_carry,$qg_string);
														}
														elseif (strpos($ssh_output3,$qr_string) == true) {
															print "<td>Linux Namespace</td><td>$qr_string</td><td><input type='checkbox' name=$qr_string></td></tr>";
															array_push($checkbox_carry,$qr_string);
														}														
													}
												}
												mysqli_data_seek($result5, 0);
											}
											elseif (strpos($row_results4[2], 'dhcp') == true) {
												print "<tr><td>$row_results1[1]</td><td>$row_results2[0]</td><td>$row_results3[1]</td><td>DHCP</td><td>DHCP Agent</td>";
												$ssh_dhcp_command = 'ip netns exec qdhcp-'.substr($row_results4[3], 41).' ip a';
												$ssh_output4 = SSH_func($ssh_dhcp_command);	
												$append_char_str = Get_first_11_char($row_results4[0]);
												$dhcp_tap_string = 'tap'.$append_char_str;
												if (strpos($ssh_output4,$dhcp_tap_string) == true) {
													print "<td>$row_results4[4]</td><td>$row_results4[0]</td><td>Linux Namespace</td><td>$dhcp_tap_string</td><td><input type='checkbox' name=$dhcp_tap_string></td></tr>";
													array_push($checkbox_carry,"qdhcp-".substr($row_results4[3], 41));
													array_push($checkbox_carry,$dhcp_tap_string);
												}
											}
										}
									}
								}
								mysqli_data_seek($result4, 0);
							}
						}
						mysqli_data_seek($result3, 0);
					}
				}
				mysqli_data_seek($result2, 0);
			}
			print "</table><br>";
		}

		mysqli_close($keystone_project);
		mysqli_close($neutron_networks);
		mysqli_close($neutron_subnets);
		mysqli_close($neutron_ports_ip);
		mysqli_close($neutron_routers);
		mysqli_close($nova_instances);


		$ovs_br_str = SSH_func('ovs-vsctl list-br');
		$ovs_br_array = explode(PHP_EOL,$ovs_br_str);
		if ($ovs_br_str !== '') {
			print "<h1>Open vSwitch Bridges</h1>It is not possible to directly monitor an internal Open vSwitch interface/bridge, therefore we must use a dummy network device and 'snoop' the interface instead<br><br>";
			print "<table border='1'><tr><td><b>OVS Name:</b></td><td><b>Description:</b></td><td><b>Attached Ports:</b></td><td><b>TCPDUMP:</b></td></tr>";
			foreach ($ovs_br_array as $line) {
				$ovs_br_port = substr(str_replace("\n",", ", SSH_func('ovs-vsctl list-ports '.$line)),0,-2);
				print "<tr>";
				if ($line == 'br-int') {
					print "<td>$line</td><td>OvS internal bridge that is at the core of OpenStack Neutron</td><td>$ovs_br_port</td><td><input type='checkbox' name=$ovs_br_port></td></tr>";
					array_push($checkbox_carry,$ovs_br_port);
				}
				elseif ($line == 'br-tun') {
					print "<td>$line</td><td>OvS tunnel bridge that is used to stage/facilitate transportation to other nodes</td><td>$ovs_br_port</td><td><input type='checkbox' name=$ovs_br_port></td></tr>";
					array_push($checkbox_carry,$ovs_br_port);
				}
				elseif ($line !== '') {
					print "<td>$line</td><td>OvS bridge used to communicate with an external network - can be L3 or VLAN</td><td>$ovs_br_port</td><td><input type='checkbox' name=$ovs_br_port></td></tr>";
					array_push($checkbox_carry,$ovs_br_port);
				}
			}

			print "</table>";

		}

		$post_checkbox_carry = implode(",",$checkbox_carry);
		print "<input type='hidden' name='port_array_hidden' value=$post_checkbox_carry></form>";

		?>

		<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>

        Jonathan Chu (12214639)<br>
        Michael Gemin (12676553)<br>

    </center>

	</body>
  
  
</html>


