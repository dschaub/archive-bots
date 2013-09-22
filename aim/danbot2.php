<?

 /* DanBot PHP AIM Bot
  *
  * Filename:   aimbot.php
  * Author:     Dan Schaub
  * Started:    8/21/03
  *
  * Description:
  *     A simple AIM bot that I plan on building on.
  *     It will start as a signon-message-signoff, and
  *     grow to... more stuff.
  *
  */

die("It's a demo!");

// versionage
define('VERSION', '1.3');
define('VERSION_STRING', 'DanBot PHP AIM Bot v' . VERSION);

// don't timeout
set_time_limit(0);

// don't report stupid var errors
error_reporting(E_ALL & ~E_NOTICE);

debug(1, "Setting configuration...");

//------------------------------
// Default Configuration
//------------------------------

if (!isset($server))    $server     = 'toc.oscar.aol.com';
if (!isset($port))      $port       = 9898;
if (!isset($nick))      $nick       = '***';
if (!isset($nickpass))  $nickpass   = '***';
if (!isset($info))      $info       = "DanBot PHP AIM Bot v" . VERSION . ", by <a href=\"http://www.danschaub.com\">Dan Schaub</a>.<br><br>Type <b>help</b> for help.";
if (!isset($verbose))   $verbose    = 2;

//------------------------------
// Formatting options
//------------------------------

$font = 'Tahoma';
$font_size = 2;
$color = '#000000';
$background = '#FFFFFF';

// default admin settings
// name => array(level, username, password)
$users = array( 'privatepepper615' => array('level' => 5, 'username' => 'Dan Schaub', 'password' => md5('*****')),
                'fakesn***'      => array('level' => 4, 'username' => 'Kamil ***', 'password' => md5('******'))
);

//load_user_info();

//------------------------------
// Commands, help, and levels
//------------------------------

/* AIM Levels:
 *  -1- Nothing much really
 *  -2- Voice of 2005 Members only
 *  -3- Mods
 *  -4- Admins
 *  -5- Bot admin
 */

               //   command                                  handler                      level
$commands = array(  'set access'        => array(   'function' => 'change_access',  'level' => 5    ),
                    'send raw'          => array(   'function' => 'raw_send',       'level' => 5    ),
                    'set global'        => array(   'function' => 'change_font',    'level' => 5    ),
                    'silent mode'       => array(   'function' => 'silent_mode',    'level' => 5    ),
                    'start im session'  => array(   'function' => 'im_session',     'level' => 5    ),
                    'add watch'         => array(   'function' => 'watch_list',     'level' => 4    ),
                    'remove watch'      => array(   'function' => 'watch_list',     'level' => 4    ),
                    'kill'              => array(   'function' => 'kill',           'level' => 4    ),
                    'block'             => array(   'function' => 'block',          'level' => 4    ),
                    'unblock'           => array(   'function' => 'unblock',        'level' => 4    ),
                    'send im to'        => array(   'function' => 'bot_im',         'level' => 3    ),
                    'change profile'    => array(   'function' => 'change_profile', 'level' => 2    ),
                    'set away'          => array(   'function' => 'set_away',       'level' => 2    ),
                    'set back'          => array(   'function' => 'set_away',       'level' => 2    ),
                    'latest news'       => array(   'function' => 'latest_news',    'level' => 1    ),
                    'news'              => array(   'function' => 'latest_news',    'level' => 1    ),
                    'help'              => array(   'function' => 'help',           'level' => 1    )
            );

//------------------------------
// Set some stuff up
//------------------------------

$filename           = $argv[0];                     // script filename
$status             = 0;                            // connection status
$seq                = floor(mt_rand(1, 100000));    // message sequence (initial random number)
$silenced           = 0;                            // stop bot auto-responses
$im_session_status  = 0;                            // 0 if not in an IM session, an array if we are
$away_status        = 0;                            // 'away message' if away, 0 if not
$act                = array();                      // array of actions by each user

// stored data

$fp = @fopen('info.dat', 'r');

if (!$fp) {
    debug(1, "Couldn't open info.dat.");
} else {
    $backup = trim(fread($fp, filesize('info.dat')));

    $backup = unserialize($backup);
    $info = $backup['info'];
    $away_status = $backup['away_status'];
}

//------------------------------
// Connect to server
//------------------------------

debug(1, "Connecting to $server on $port...");

$aim = @fsockopen($server, $port) or die("Couldn't open connection to $server:$port");

$status = 1;

//------------------------------
// Shake hands with server
//------------------------------

// step 1: send flapon
send("FLAPON\r\n\r\n");

// receive flap signon
in(10);

// step 2: send flap signon
debug(1, "Sending FLAP SIGNON.");
write_flap_signon();

// step 3: log on to the service
debug(1, "Sending logon info.");
write_flap('toc_signon login.oscar.aol.com 5190 ' . normalize($nick) . ' ' . roast($nickpass) . ' english danbot');

$success = read_flap();

// check for connection error
if (starts_with('ERROR:', $success)) {
    $error = parse_error($success);

    debug(0, "Error connecting to TOC server: $error");
    debug(0, "Good bye!");

    exit;
}

//------------------------------
// Read loop!
//------------------------------

while (list($type,$in) = read_flap()) {
    if (starts_with('CONFIG:', $in)) {
        list($c, $stuff) = explode(':', $in, 2);

        $rows = explode("\x0a", $stuff);    // explode by newline

        // connection step 4: initialize settings
        $config = "\"m 1\ng Buddies\nb privatepepper615\nb fakesnake00\nb katherine07452\n\"";

        write_flap("toc_set_config $config");
        write_flap('toc_add_buddy privatepepper615');
        write_flap('toc_set_info ' . encode($info));
        write_flap('toc_init_done');

        debug(0, "Successfully connected to AIM!");

        if ($away_status) {
            write_flap('toc_set_away ' . encode($away_status));
        }

        continue;
    }

    // handle errors
    if (starts_with('ERROR:', $in)) {
        $error = parse_error($in);

        debug(1, "Error: $error");

        continue;
    }

    // get correct nickname format
    if (starts_with('NICK:', $in)) {
        list($blank, $formatnick) = explode(':', $in, 2);
        $nick = $formatnick;
    }

    // handle incoming messages
    if (starts_with('IM_IN:', $in)) {

        // IM_IN:<from>:<auto response T/F>:message
        list($imin, $from, $auto, $message) = explode(':', $in, 4);
        $nfrom = normalize($from);

        $message = remove_html($message);
        $message = html_entity_decode($message, ENT_QUOTES);

        // output the message
        if ($auto == 'T') {
            debug(1, "*** Auto-response from $from: $message");
        } else {
            debug(1, "$from: $message");
        }

        if (isset($watch_list[$nfrom])) {
            send_im($watch_list[$nfrom], "$nfrom said: $message");
        }

        // handle bot silencing
        if ($silenced == 1 and $users[$nfrom]['level'] < 5) {
            continue;
        }

        // handle away status
        if (is_string($away_status) and $users[$nfrom]['level'] < 4) {
            write_flap('toc_send_im ' . normalize($from) . ' ' . encode($away_status) . ' auto');

            continue;
        }

        // handle im sessions (too many handles, damnit)
        if (is_array($im_session_status) and ($nfrom == $im_session_status[0] or $nfrom == $im_session_status[1])) {
            im_session(array('message' => $message, 'from' => $from));

            continue;
        }

        // handle multi-part commands
        if ($act[$nfrom]['in_command']) {
            if (function_exists($act[$nfrom]['in_command'])) {
                $act[$nfrom]['in_command']( array('from' => $from, 'message' => $message, 'command' => $act[$nfrom]['in_command'], 'args' => $message) );
            } else {
                send_im($from, "Bot bug! Handler function not found: {$act[$nfrom]['in_command']}.");
            }

            continue;
        }

        // check to see if the message contains a command
        $is_command = is_command($message);

        if ($is_command) {
            list($command, $args) = $is_command;

            // user validation
            debug(2, "Validating $from for $command.");
            if (get_permission($from) >= $commands[$command]['level']) {
                debug(2, "$from validated for \"$command\" (level {$commands[$command]['level']}).");

                // make sure the handler function exists, then execute
                if (function_exists($commands[$command]['function'])) {
                    $stop = $commands[$command]['function']( array('from' => $from, 'message' => $message, 'command' => $command, 'args' => $args) );
                } else {
                    send_im(from, "Bot bug! Handler function not found: {$commands[$command]['function']}.");
                    $stop = 0;
                }

                if ($stop) {
                    break;
                } else {
                    continue;
                }
            } else {
                send_im($from, "You don't have permission to execute that command. Type <b>help $command</b> to find out who does.");
            }
        } else {
            // handle greetings
            if (preg_match('/^(hi|hey|yo|word|hello|sup|werd|wurd|greetings)$/i', $message)) {
                if (isset($users[$nfrom])) {
                    send_im($from, "Welcome back, {$users[$nfrom]['username']}! How can I help you?");
                } else {
                    send_im($from, "Hey, $from. Before you do anything else, it's a good idea to map your screenname to a forum account on the Voice of 2005 boards. Just type &quot;register&quot; to get started.<br><br>You MUST have an existing APPROVED account on Voice of 2005.com.");
                }

                continue;
            }

            // handle spanish (hehehehe)
            if (preg_match('/^hola/i', $message)) {
                if (isset($users[$nfrom])) {
                    send_im($from, "Que pasa, {$users[$nfrom]['username']}?");
                } else {
                    send_im($from, "Que pasa, $from?");
                }

                continue;
            }

            // let people see what their level is
            if (preg_match('/^(what\'s |what is )?my level/i', $message)) {
                if (isset($users[$nfrom])) {
                    send_im($from, "Your user level is set to {$users[$nfrom]['level']} ({$leveldesc[$users[$nfrom]['level']][0]}).");
                } else {
                    send_im($from, "Your user level is set to 1 (everyone).");
                }

                continue;
            }

            // handle insults
            if (preg_match('/^fuck you|shut up|(you(\'re| are)? ((a|an) )?(ass( ?(wipe)?)|piece of shit|gay|fag|dick|cock))/i', $message)) {
                send_im($from, "What a sad life you lead, if you are forced to throw insults at an inanimate object. \"$message\" to you, too, punk.");

                continue;
            }

            // the best part: handle death threats
            if (preg_match('/^(die|go kill (ur|your) ?self)/i', $message)) {
                if ($act[$nfrom]['has_killed'] == 1) {
                    send_im($from, "You only get to see that performance once in my lifetime. Why would you want me to kill myself so many times? Huh? Do you hate me? Maybe I just will! What would you think of that?<br><br>No, I won't give you the satisfaction, you sick bastard.");
                } else {
                    send_im($from, "You want me to die? B-b-but... I-I thought... well, fine. If that's the way you want it. I think I just might kill myself. So long, cruel world. May you all rot in hell with me.<br><br>*Jumps off the George Washington Bridge*");

                    sleep(2);
                    write_flap("toc_set_config {m 4\nd $from\n}");
                    sleep(5);
                    write_flap("toc_set_config {m 4\np $from\n}");

                    send_im($from, "ROFL! Did you see the look on your face!? You thought I was actually gonna kill myself! AHAHAHAHAHA... woooo... that was a good one.<br><br>*walks away chuckling*");

                    $act[$nfrom]['has_killed'] = 1;
                }

                continue;
            }

            // handle laughter
            if (preg_match('/^((ha)+|l(ol)+|rofl(mao)?|lmao)$/i', $message)) {
                send_im($from, "That's right. Hilarious, aren't I.");
                continue;
            }

            // handle come-ons
            if ($message == ':-*') {
                send_im($from, "*purr*");
                continue;
            }

            if (preg_match('/^(wanna|want to) (fuck|have sex)(with? me)?\??$/i', $message)) {
                send_im($from, "HELL FUCKING YES! If you're a girl, that is. :-/");
                continue;
            }

            // handle boredom
            if (preg_match("/^i('?m| am)( so)? bored$/i", $message)) {
                send_im($from, "How about a dance?<br><br><font face=\"courier new\" size=\"2\">:D/-&lt;<br>:D|-&lt;<br>:D\\-&lt;<br>:D|-&lt;<br>:D|&gt;<br>:D/-&lt;<br>:D\\-&lt;<br>:D|-&lt;<br>:D&gt;<br>:D&gt;-&lt;</font>");
                continue;
            }

            if ($away_status) {
                send_im($from, $away_status);
            } else {
                send_im($from, 'No news.');
            }

        }
    }
        
}

debug(0, 'All done! Closing connection...');

fclose($aim);

debug(0, 'Saving data...');

save_data();

debug(0, 'Good bye!');

//------------------------------
// Command handlers
//------------------------------

function raw_send($data) {
    write_flap($data['args']);

    return 0;
}

function kill($data) {
    return 1;
}

function change_font($data) {
    list($what, $val) = explode(' ', $data['args'], 2);

    global $$what;
    $$what = $val;

    send_im($data['from'], "$what changed to $val");
}

function silent_mode($data) {
    global $silenced;

    if ($data['args'] == 'on') {
        $silenced = 1;
    } else {
        $silenced = 0;
    }

    send_im($data['from'], "Silent mode set to \"$data[args]\"");
}

function bot_im($data) {
    global $act;

    $uact = &$act[normalize($data['from'])];

    if (empty($uact['bot_im_step'])) {
        $uact['bot_im_to'] = normalize($data['args']);
        $uact['bot_im_step'] = 1;
        $uact['in_command'] = 'bot_im';

        send_im($data['from'], "OK, enter the message you'd like to send to {$uact['bot_im_to']}.");

        return 0;
    }

    if ($uact['bot_im_step'] == 1) {
        send_im($uact['bot_im_to'], $data['message']);
        send_im($data['from'], "$uact[bot_im_to]&gt; $data[message]");

        unset($uact['bot_im_to'], $uact['bot_im_step']);
        $uact['in_command'] = 0;

        return 0;
    }
}

function im_session($data) {
    global $im_session_status;

    if (!$im_session_status) {
        $im_session_status = array(normalize($data['args']), normalize($data['from']));
        send_im($data['from'], "OK, IM session established with $im_session_status[0].");

        return 0;
    }

    if ($data['message'] == '__END__' or $data['message'] == 'ANCHOVIES!') {
        $im_session_status = 0;
        send_im($data['from'], "IM session terminated.");

        return 0;
    }

    if (normalize($data['from']) == $im_session_status[1]) {
        send_im($im_session_status[0], $data['message']);
    } else {
        send_im($im_session_status[1], $data['message']);
    }

    return 0;
}

function watch_list($data) {
    global $watch_list;

    if ($data['command'] == 'add watch') {
        $watch_list[$data['args']] = $data['from'];
        send_im($data['from'], "$data[args] added to your watch list.");
    } else {
        unset($watch_list[$data['args']]);
        send_im($data['from'], "$data[args] removed from your watch list.");
    }
}

function set_away($data) {
    global $away_status, $background, $font, $font_size, $color;

    if ($data['command'] == 'set back') {
        $away_status = 0;

        write_flap('toc_set_away');
    }

    if ($data['command'] == 'set away' and $data['args'] != '') {
        $away_status = "<HTML><BODY BGCOLOR=\"$background\"><FONT FACE=\"$font\" SIZE=\"$font_size\" COLOR=\"$color\">$data[args]</FONT></BODY></HTML>";

        write_flap('toc_set_away ' . encode($away_status));
    }

    return 0;
}

function change_profile($data) {
    global $info, $background, $font, $font_size, $color;

    $info = "<HTML><BODY BGCOLOR=\"$background\"><FONT FACE=\"$font\" SIZE=\"$font_size\" COLOR=\"$color\">$data[args]</FONT></BODY></HTML>";
    write_flap('toc_set_info ' . encode($info));

    return 0;
}

function change_access($data) {
    global $users, $db;

    list($who, $level) = explode(' ', $data['args']);
    $who = normalize($who);

    if (!isset($users[$who])) {
        send_im($data['from'], "No one has registered with screenname $who.");

        return 0;
    }

    debug(2, "Changing access level for $who to $level");
    $users[$who]['level'] = intval($level);

    send_im($data['from'], "$who's access level changed to $level.");
    send_im($who, "Your access level has been change to $level by $data[from].");

    return 0;
}

function block($data) {
    global $users;

    $config = "{m 4\n";
    $block = explode(' ', $data['args']);

    for ($i = 0; $i < count($block); $i++) {
        if (isset($users[normalize($block[$i])]) and $users[normalize($block[$i])]['level'] >= 4) {
            send_im($data['from'], "$block[$i] is an admin, you can't block an admin!");
        } else {
            $config .= 'd ' . normalize($block[$i]) . "\n";
        }
    }

    $config .= '}';
    write_flap("toc_set_config $config");
    send_im($data['from'], "Blocked: $data[args]");
}

function unblock($data) {
    $config = "{m 4\n";
    $block = explode(' ', $data['args']);

    for ($i = 0; $i < count($block); $i++) {
        $config .= 'p ' . normalize($block[$i]) . "\n";
    }

    $config .= '}';
    write_flap("toc_set_config $config");
    send_im($data['from'], "Un-blocked: $data[args]");
}

function latest_news($data) {
    global $away_status;

    if ($away_status) {
        send_im($data['from'], $$away_status);
    }
}

function help($data) {
    global $help, $commands, $leveldesc, $users;

    if ($data['args'] == 'levels') {
        $message1 = 'In order to keep this bot running, there has to be seperation between the average user ';
        $message1 .= 'and those who are in charge of keeping it together. If users could do what the admins could ';
        $message1 .= 'do, they would be able to completely ruin the bot and how it runs, not to mention do damage ';
        $message1 .= 'to the Voice of 2005 web site. Below is a brief explanation of each level.';

        $message2 = '<br>';
        foreach ($leveldesc as $level => $info) {
            $message2 .= "<b>Level $level: $info[0]</b><br>$info[1]<br><br>";
        }

        $message2 = substr($message2, 0, -4);

        send_im($data['from'], $message1);
        send_im($data['from'], $message2);

        return 0;
    }

    if ($data['args'] == '' or preg_match('/^level/', $data['args'])) {
        if ($data['args'] != '') {
            list($blank, $level) = explode(' ', $data['args']);
        }

        $list = 'Help is available for the following commands:<br><br>';
        foreach ($help as $command => $cdata) {
            if (($data['args'] != '' and $commands[$command]['level'] == $level) or ($data['args'] == '' and $commands[$command]['level'] <= $users[normalize($data['from'])]['level'])) {
                $list .= "$command, ";
            }
        }

        $list = substr($list, 0, -2) . '<br><br>';
        $list .= 'For an explanation of user levels, type <b>help levels</b>.';

        send_im($data['from'], $list);
        return 0;
    }

    if (!isset($help[$data['args']])) {
        send_im($data['from'], "No help data for $data[args].");

        return 0;
    }

    $info = $help[$data['args']];
    $message = "Help available for {$help[$data['args']]}:<br><br>";
    $message .= "<b>Usage:</b> $data[args] " . htmlentities($info[1]) . "<br>";
    $message .= "<b>Level:</b> {$leveldesc[$commands[$data['args']]['level']][0]}<br>";
    $message .= "<b>Description:</b><br>$info[0]";

    send_im($data['from'], $message);
}

//------------------------------
// Functions and such
//------------------------------

function send($msg) {
    global $aim;

    debug(4, "Sleeping for two seconds...");

    sleep(2);

    debug(3, "Sending to stream: $msg");

    fputs($aim, $msg);
}

function write_flap($toc, $type = 2) {
    global $aim, $seq;

    if (strlen($toc) > 2048) {
        debug(1, "Message is too long to send: $toc");

        return;
    }

    debug(2, "Sending: $toc");

    $toc .= "\0";
    $msg = pack('aCnna*', '*', $type, $seq, strlen($toc), $toc);

    send($msg);

    $seq++;
}

function read_flap() {
    global $aim;

    $start = in();

    // all commands start with 0x2a (ASCII '*')
    if ($start != '*') {
        debug(1, "Error: Invalid FLAP header received.");

        if ($start == '') {
            return 0;
        }

        return 'Invalid FLAP received.';
    }

    // next three bytes are frame type and sequence number
    $type = unpack('abyte', in(1));
    $type = $type['byte'];

    in(2);

    // next two bytes specify the length
    $stuff = unpack('nbyte', in(2));

    $message = in($stuff['byte']);

    debug(2, "Received: $message");

    return array($type, $message);
}

function write_flap_signon() {
    global $aim, $nick, $seq;

    $data = pack('Nnna' . strlen($nick), 1, 1, strlen($nick), $nick);

    $msg = pack('aCnn', '*', 1, $seq, strlen($data));
    $msg .= $data;

    send($msg);

    $seq++;
}

function send_im($to, $message) {
    global $nick, $font, $font_size, $color, $background;

    debug(1, "$nick ($to): $message");

    $message = "<HTML><BODY BGCOLOR=\"$background\"><FONT FACE=\"$font\" SIZE=\"$font_size\" COLOR=\"$color\">$message</FONT></BODY></HTML>";

    write_flap('toc_send_im ' . normalize($to) . ' ' . encode($message));
}

function in($l = 1) {
    global $aim;

    $stuff = fread($aim, $l);

    //debug(3, "Read $l bytes from the stream: $stuff");

    return $stuff;
}

function is_command($message) {
    global $commands;

    debug(2, "Looking up command in \"$message\"");
    foreach ($commands as $c => $s) {
        if (starts_with($c, strtolower($message))) {
            $args = substr($message, strlen($c) + 1);

            debug(2, "\"$message\" contains $c, returning \"$c\" and \"$args\"");
            return array($c, $args);
        }
    }

    return 0;
}

function remove_html($txt) {
    return preg_replace('!</?[^<>]+>!U', '', $txt);
}

function get_permission($user) {
    global $users;

    $user = normalize($user);

    if (!isset($users[$user])) {
        return 1;
    } else {
        return $users[$user]['level'];
    }
}

function starts_with($find, $str) {
    if (substr($str, 0, strlen($find)) == $find) {
        return 1;
    } else {
        return 0;
    }
}

function parse_error($in) {
    $errors = array(

        /* General Errors */
        '901' => '$arg is not currently available.',
        '902' => 'Warning of $arg currently unavailable.',
        '903' => 'A message has been dropped, you are exceeding the server speed limit.',

        /* Admin Errors */
        '911' => 'Error validating input.',
        '912' => 'Invalid account.',
        '913' => 'Error encountered while processing request.',
        '914' => 'Service unavailable.',

        /* Chat Errors */
        '950' => 'Chat in $arg is unavailable.',

        /* IM & Info Errors */
        '960' => 'You are sending messages too fast to $arg.',
        '961' => 'You missed an IM from $arg because it was too big.',
        '962' => 'You missed an IM from $arg because it was sent too fast.',

        /* Directory Errors */
        '970' => 'Operation failed.',
        '971' => 'Too many matches.',
        '972' => 'Need more qualifiers.',
        '973' => 'Directory service temporarily unavailable.',
        '974' => 'Email lookup restricted.',
        '975' => 'Keyword ignored.',
        '976' => 'No keywords.',
        '977' => 'Language not supported.',
        '978' => 'Country not supported.',
        '979' => 'Failutre: Unknown $arg',

        /* Auth errors */
        '980' => 'Incorrect nickname or password.',
        '981' => 'The service is temporarily unavailable.',
        '982' => 'Your warning level is currently too high to sign on.',
        '983' => 'You have been connecting and disconnecting too frequently. Wait 10 minutes and try again. If you continue to try, you will have to wait longer.',
        '989' => 'Unknown signon error has occured: $arg'

    );

    list($error, $num, $arg) = explode(':', $in, 3);

    $errtext = str_replace('$arg', $arg, $errors[$num]);

    return $errtext;
}

function normalize($nick) {
    $nick = str_replace(' ', '', $nick);
    $nick = strtolower($nick);

    return $nick;
}

function encode($msg) {
    $msg = str_replace("\\", "\\\\", $msg);
    $msg = str_replace("\"", "\\\"", $msg);
    $msg = str_replace("\$", "\\\$", $msg);
    $msg = str_replace('[', "\\[", $msg);
    $msg = str_replace(']', "\\]", $msg);
    $msg = str_replace('{', "\\{", $msg);
    $msg = str_replace('}', "\\}", $msg);
    $msg = str_replace('(', "\\(", $msg);
    $msg = str_replace(')', "\\)", $msg);

    return '"' . $msg . '"';
}

function roast($pass) {
    $roast = 'Tic/Toc';

    for ($i = 0; $i < strlen($roast); $i++) {
        $roast_string[$i] = ord(substr($roast, $i, 1));
    }

    for ($i = 0; $i < strlen($pass); $i++) {
        $pass_string[$i] = ord(substr($pass, $i, 1));
    }

    $roasted = '0x';
    for ($i = 0; $i < count($pass_string); $i++) {
        $roasted .= sprintf('%02x', ((int)$pass_string[$i] ^ (int)$roast_string[$i % 7]));
    }

    return $roasted;
}

function debug($level, $message) {
    global $log, $verbose;

    if ($verbose >= $level) {
        echo "$message\n";
    }

    if (!$log) {
        $log = @fopen('DanBot.log', 'a');

        if (!$log) {
            echo "couldnt open teh log!11!!1!1 OMG WTF LOL\n";
        } else {
            fputs($log, "Logging started: " . date('r') . "\n");
        }
    }

    if ($log) {
        fputs($log, "[" . date('d M Y H:i:s') . " ($level)] $message\n");
    }

    if ($message == 'Good bye!') {
        fputs($log, "Logging stopped: " . date('r') . "\n\n");
        fclose($log);
    }
}

function load_user_info() {
    global $db, $users;

    debug(2, "Loading user info.");

    $result = $db->query('select * from aim_users');
    while ($user = $db->fetch_array($result)) {
        debug(2, "Loading user info for $user[sn] ($user[username])");
        $users[$user['sn']] = array('level' => $user['level'], 'username' => $user['username'], 'password' => $user['password']);
    }
}

function save_data() {
    global $info, $away_status;

    $fp = @fopen('info.dat', 'w');

    if (!$fp) {
        debug(1, "Couldn't open backup file for writing.");
    } else {
        fputs($fp, serialize(array('info' => $info, 'away_status' => $away_status)));
        @fclose($fp);

        debug(1, "Profile and away status backed up successfully.");
    }
}

?>
