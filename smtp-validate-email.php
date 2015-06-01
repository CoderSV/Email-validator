<?php
function validateEmailSmtp($email, $probe_address="", $debug=false) {
    # --------------------------------
    # function to validate email address
    # through a smtp connection with the
    # mail server.
    # by Giulio Pons
    # http://www.barattalo.it
    # --------------------------------
    $output = "";
    # --------------------------------
    # Check syntax with regular expression
    # --------------------------------
    if (!$probe_address) $probe_address = $_SERVER["SERVER_ADMIN"];
    if (preg_match('/^([a-zA-Z0-9\._\+-]+)\@((\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,7}|[0-9]{1,3})(\]?))$/', $email, $matches)) {
        $user = $matches[1];
        $domain = $matches[2];
        # --------------------------------
        # Check availability of DNS MX records
        # --------------------------------
        if (function_exists('checkdnsrr')) {
            # --------------------------------
            # Construct array of available mailservers
            # --------------------------------
            if(getmxrr($domain, $mxhosts, $mxweight)) {
                for($i=0;$i<count($mxhosts);$i++){
                    $mxs[$mxhosts[$i]] = $mxweight[$i];
                }
                asort($mxs);
                $mailers = array_keys($mxs);
            } elseif(checkdnsrr($domain, 'A')) {
                $mailers[0] = gethostbyname($domain);
            } else {
                $mailers=array();
            }
            $total = count($mailers);
            # --------------------------------
            # Query each mailserver
            # --------------------------------
            if($total > 0) {
                # --------------------------------
                # Check if mailers accept mail
                # --------------------------------
                for($n=0; $n < $total; $n++) {
                    # --------------------------------
                    # Check if socket can be opened
                    # --------------------------------
                    if($debug) { $output .= "Checking server $mailers[$n]...\n";}
                    $connect_timeout = 2;
                    $errno = 0;
                    $errstr = 0;
                    # --------------------------------
                    # controllo probe address
                    # --------------------------------
                    if (preg_match('/^([a-zA-Z0-9\._\+-]+)\@((\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,7}|[0-9]{1,3})(\]?))$/', $probe_address,$fakematches)) {
                        $probe_domain = str_replace("@","",strstr($probe_address, '@'));
 
                        # --------------------------------
                        # Try to open up socket
                        # --------------------------------
                        if($sock = @fsockopen($mailers[$n], 25, $errno , $errstr, $connect_timeout)) {
                            $response = fgets($sock);
                            if($debug) {$output .= "Opening up socket to $mailers[$n]... Success!\n";}
                            stream_set_timeout($sock, 5);
                            $meta = stream_get_meta_data($sock);
                            if($debug) { $output .= "$mailers[$n] replied: $response\n";}
                            # --------------------------------
                            # Be sure to set this correctly!
                            # --------------------------------
                            $cmds = array(
                                "HELO $probe_domain",
                                "MAIL FROM: <$probe_address>",
                                "RCPT TO: <$email>",
                                "QUIT",
                            );
                            # --------------------------------
                            # Hard error on connect -> break out
                            # --------------------------------
                            if(!$meta['timed_out'] && !preg_match('/^2\d\d[ -]/', $response)) {
                                $codice = trim(substr(trim($response),0,3));
                                if ($codice=="421") {
                                    //421 #4.4.5 Too many connections to this host.
                                    $error = $response;
                                    break;
                                } else {
                                    if($response=="" || $codice=="") {
                                        //c'è stato un errore ma la situazione è poco chiara
                                        $codice = "0";
                                    }
                                    $error = "Error: $mailers[$n] said: $response\n";
                                    break;
                                }
                                break;
                            }
                            foreach($cmds as $cmd) {
                                $before = microtime(true);
                                fputs($sock, "$cmd\r\n");
                                $response = fgets($sock, 4096);
                                $t = 1000*(microtime(true)-$before);
                                if($debug) {$output .= "$cmd\n$response" . "(" . sprintf('%.2f', $t) . " ms)\n";}
                                if(!$meta['timed_out'] && preg_match('/^5\d\d[ -]/', $response)) {
                                    $codice = trim(substr(trim($response),0,3));
                                    if ($codice<>"552") {
                                        $error = "Unverified address: $mailers[$n] said: $response";
                                        break 2;
                                    } else {
                                        $error = $response;
                                        break 2;
                                    }
                                    # --------------------------------
                                    // il 554 e il 552 sono quota
                                    // 554 Recipient address rejected: mailbox overquota
                                    // 552 RCPT TO: Mailbox disk quota exceeded
                                    # --------------------------------
                                }
                            }
                            fclose($sock);
                            if($debug) { $output .= "Succesful communication with $mailers[$n], no hard errors, assuming OK\n";}
                            break;
                        } elseif($n == $total-1) {
                            $error = "None of the mailservers listed for $domain could be contacted";
                            $codice = "0";
                        }
                    } else {
                        $error = "Il probe_address non è una mail valida.";
                    }
                }
            } elseif($total <= 0) {
                $error = "No usable DNS records found for domain '$domain'";
            }
        }
    } else {
        $error = 'Address syntax not correct';
    }
    if($debug) {
        print nl2br(htmlentities($output));
    }
    if(!isset($codice)) {$codice="n.a.";}
    if(isset($error)) return array($error,$codice); 
	else return true;
}
