<?

 /* DanBot PHP IRC Bot
  *
  * Filename:   ircbot.php
  * Author:     Dan Schaub
  * Updated:    8/21/03
  *
  * Description:
  *     A simple IRC bot that I plan on building on.
  *     It will start as a signon-message-signoff, and
  *     grow to... more stuff.
  *
  */

// make sure the demo doesn't actually run
die("It's just a demo!");

// versionage
define('VERSION', '1.3.5');
define('VERSION_STRING', 'DanBot PHP IRC Bot v' . VERSION);

// don't timeout
set_time_limit(0);

// don't report stupid var errors
error_reporting(E_ALL & ~E_NOTICE);

debug(1, "Internal: Setting configuration...");

$configversion = "DanBot Config v1.0.1";

if ($argv[1] == '-noconfig') {
    require('default.conf');
} elseif ($argv[1] == '-config' and file_exists($argv[2])) {
    require($argv[2]);
} else {
    $launchconfig = 1;
}

//------------------------------
// Default Configuration
//------------------------------

if (!isset($dbhost))    $gbhost     = 'localhost';
if (!isset($dbuser))    $dbuser     = 'root';
if (!isset($dbpass))    $dbpass     = '';
if (!isset($db))        $db         = 'DanBot';

if (!isset($server))    $server     = 'irc.chatspike.net';
if (!isset($port))      $port       = 6667;
if (!isset($nick))      $nick       = 'Dan|Bot';
if (!isset($nickpass))  $nickpass   = '***';
if (!isset($user))      $user       = 'danbot';
if (!isset($info))      $info       = 'DanBot PHP IRC Bot v' . VERSION . '!';
if (!isset($verbose))   $verbose    = 2;
if (!isset($autojoin))  $autojoin   = array('#nethack');

$saveconfig = '';

$restartwithconfig = 0;

if ($launchconfig) {
    debug(1, "Internal: Starting... The Configuratah! *doo doo DOO!*");
    $filename = configurator();

    // check to make sure it's a good config file
    if ($filename) {
        if (file_exists($filename)) {
            debug(0, "Checking file integrity...");

            $fp = @fopen($filename, 'r');
            if (!$fp) {
                debug(0, "Couldn't open config file for inspection. Continuing with default configuration.");
            } else {
                $head = trim(fgets($fp, 256));

                if ($head != ("<" . "? /* $configversion */")) {
                    debug(0, "Invalid configuration header: $head (current config version: $configversion).");
                } else {
                    $restartwithconfig = $filename;

                    debug(0, "Using configuration file $filename.");
                    require($filename);
                }
            }
        } else {
            debug(0, "Config file $filename doesn't exist. Restart? [y/n] ");
            $choice = readin();

            if ($choice == 'y') {
                restart_bot();
            } else {
                debug(0, "OK, running with default configuration.");
            }
        }
    }
}

//------------------------------
// Database connection
//------------------------------

require('MySQL_Connection.php');

debug(1, "Internal: Connecting to MySQL database...");

$db = new MySQL_Connection(array('hostname' => $dbhost, 'username' => $dbuser, 'password' => $dbpass, 'database' => $db));

// default admin settings
// name => array(password, level)
$users = array( 'Dan'       => array('level' => 5, 'password' => md5('***')),
                'Dan|MIA'   => array('level' => 5, 'password' => md5('***')),
                'CrazyMofo' => array('level' => 5, 'password' => md5('***')) );

load_user_info();

$ids = array(); // ID'd hostmasks

/** Levels:
 **  1 - Everyone
 **  2 - Registered users - can use personalized stuff
 **  3 - Quasi-ops - get auto-voiced, but can !kick
 **  4 - Ops - get auto-op, can !kick and !ban
 **  5 - Admin - can do everything
 **/

               //   command                              handler                      level
$commands = array(  '!join'         => array(   'function' => 'joinchannel',    'level' => 5    ),
                    '!part'         => array(   'function' => 'partchannel',    'level' => 5    ),
                    '!kill'         => array(   'function' => 'kill',           'level' => 5    ),
                    '!access'       => array(   'function' => 'change_access',  'level' => 5    ),
                    '!restart'      => array(   'function' => 'restart_bot',    'level' => 5    ),
                    '!handler'      => array(   'function' => 'inline_handler', 'level' => 5    ),
                    '!raw'          => array(   'function' => 'raw',            'level' => 5    ),
                    '!define'       => array(   'function' => 'new_handler',    'level' => 5    ),
                    '!say'          => array(   'function' => 'say',            'level' => 4    ),
                    '!msg'          => array(   'function' => 'say',            'level' => 4    ),
                    '!op'           => array(   'function' => 'mode',           'level' => 4    ),
                    '!opme'         => array(   'function' => 'mode',           'level' => 4    ),
                    '!deop'         => array(   'function' => 'mode',           'level' => 4    ),
                    '!voice'        => array(   'function' => 'mode',           'level' => 4    ),
                    '!devoice'      => array(   'function' => 'mode',           'level' => 4    ),
                    '!moderate'     => array(   'function' => 'mode',           'level' => 4    ),
                    '!mod'          => array(   'function' => 'mode',           'level' => 4    ),
                    '!unmoderate'   => array(   'function' => 'mode',           'level' => 4    ),
                    '!unmod'        => array(   'function' => 'mode',           'level' => 4    ),
                    '!ban'          => array(   'function' => 'mode',           'level' => 4    ),
                    '!unban'        => array(   'function' => 'mode',           'level' => 4    ),
                    '!bannick'      => array(   'function' => 'ban_nick',       'level' => 4    ),
                    '!kick'         => array(   'function' => 'kick',           'level' => 3    ),
                    '!trillstick'   => array(   'function' => 'add_sticky',     'level' => 3    ),
                    '!bash'         => array(   'function' => 'bashit',         'level' => 1    ),
                    '!version'      => array(   'function' => 'version',        'level' => 1    ),
                    '!ident'        => array(   'function' => 'ident',          'level' => 1    ),
                    '!login'        => array(   'function' => 'ident',          'level' => 1    ),
                    '!unident'      => array(   'function' => 'unident',        'level' => 1    ),
                    '!logout'       => array(   'function' => 'unident',        'level' => 1    ),
                    '!trillian'     => array(   'function' => 'trillian_forums','level' => 1    ),
                    '!help'         => array(   'function' => 'help',           'level' => 1    ),
                    '!google'       => array(   'function' => 'google',         'levle' => 1    )
            );

$help = array(
    '!bash' => 'Type !bash to get a random quote. Type !bash <num> to get quote #<num>. Type !bash search=<stuff> to search for <stuff>, with spaces replaced by +\'s (URL encoded).',
    '!ident' => 'Type !ident <password> to identify yourself. If you aren\'t a known member, this will create a new account with password <password> for your current nickname. An alias is !login.',
    '!unident' => 'Type !unident (or !logout) to remove yourself from the member cache. You will not longer be able to execute member-only commands.',
    '!trillian' => 'Type !trillian to get a list of threads from Trillian\'s General Discussion board. Tpye !trillian <limit> to get the last <limit> threads. Type !trillian <limit> <forumid> to change to a different forum, i.e. skinning or tech support. Type !trillian <limit> <forumid> <server> to change the server, i.e. to trillian.cc.'
);

//------------------------------
// Set some stuff up
//------------------------------

$filename   = $argv[0];

$channels   = array();
$status     = 0;
$autojoined = 0;
$nickident  = 0;

//------------------------------
// Connect to server
//------------------------------

debug(1, "Internal: Connecting to $server on $port...");

$irc = @fsockopen($server, $port) or die("Couldn't open connection to $server:$port");

$status = 1;

//------------------------------
// Send registration
//------------------------------

debug(1, "Internal: Sending registration...");

send("NICK $nick");
send("USER $user 0 * :$info");

//------------------------------
// Enter read loop
//------------------------------

debug(1, "Internal: Entering read loop...");

while ($irc) {
    $in = read_incoming();

    $parts = explode(' ', $in);

    // take care of pings
    if (strtoupper($parts[0]) == 'PING') {
        debug(1, "Ping? Pong!");

        send("PONG $parts[1]");
        continue;
    }

    // has the server given us a good-to-go?
    if ($parts[1] == '001' and $status == 1) {
        $status = 2;
    }

    if ($status == 2 and !$nickident) {
        if (isset($nickpass) and $nickpass != '') {
            debug(1, "Identifying with NickServ...");
            privmsg('NickServ', "identify $nickpass");

            $nickident = 1;
        }
    }        

    if ($status == 2 and !$autojoined) {
        // auto join channels
        debug(1, "Internal: autojoining...");

        $aj = implode(',', $autojoin);
        send("JOIN $aj");

        debug(1, "Autojoin done!");

        $autojoined = 1;
    }

    // get message info
    $user = substr($parts[0], 1);
    list($usernick, $hostmask) = explode('!', $user);

    $command = strtoupper($parts[1]);

    //------------------------------
    // Handle nick list
    //------------------------------

    /************************* This still needs some work *****************************
    if ($command == '353') {
        $list = substr($in, strpos($in, ':', strpos($in, ':') + 1) + 1);

        $channel = $parts[4];
        $names = explode(' ', $list);

        for ($i = 0; $i < count($names); $i++) {
            $m = substr($names[$i], 0, 1);
            if (preg_match('/(@|\+|%)/', $m, $match)) {
                $channels[$channel][$match[1]][] = $names[$i];

                if ($verbose >= 3) {
                    echo "Added $names[$i] to $channel's $match[1] list.\n";
                }
            } else {
                $channels[$channel]['users'] = $names[$i];
            }
        }
    }
    ***********************************************************************************/

    //------------------------------
    // Handle joins (more later)
    //------------------------------

    if ($command == 'JOIN' or $command == 'PART') {
        if ($command == 'JOIN') {
            $channel = substr($parts[2], 1);
        }

        debug(1, "*** $usernick has " . strtolower($command) . "ed $channel");

        if ($command == 'JOIN') {
            if ($users[$usernick]['level'] == -1) {
                send("KICK $channel $usernick :Auto-kick!");
                continue;
            }

            (integer)$level = match_mask($user);

            if ($level >= 4) {
                send("MODE $channel +o $usernick");
            } elseif ($level == 3) {
                send("MODE $channel +v $usernick");
            }
        }
    }

    if ($command == 'NICK') {
        debug(1, "*** $usernick is now known as " . substr($parts[2], 1));
    }

    //------------------------------
    // Handle private messages
    // (channels and users)
    //------------------------------

    if ($command == 'NOTICE') {
        $message = substr($in, strpos($in, ':', strpos($in, ':') + 1) + 1);
        debug(1, "-{$usernick}- $message");

        continue;
    }

    if ($command == 'PRIVMSG') {
        $to = $parts[2];
        $message = substr($in, strpos($in, ':', strpos($in, ':') + 1) + 1);

        //debug(2, "Incoming message:");
        //debug(2, "From:     $usernick");
        //debug(2, "To:       $to");
        //debug(2, "Hostmask: $hostmask");
        //debug(2, "Message:  $message");

        $mparts = explode(' ', $message);

        if (preg_match("/\x01(VERSION|PING|ACTION|TIME)(?: (.*))?\x01/", $message, $matches)) {
            if ($matches[1] == 'VERSION') {
                debug(1, "$usernick requested bot version.");
                notice($usernick, "\x01VERSION " . VERSION_STRING . "\x01");
            } elseif ($matches[1] == 'PING') {
                debug(1, "$usernick sent a ping request.");
                notice($usernick, "\x01PING $matches[2]\x01");
            } elseif ($matches[1] == 'TIME') {
                debug(1, "$usernick requested the local time.");
                notice($usernick, "\x01TIME " . strftime("%a %b %d %H:%M:%S") . "\x01");
            } else {
                debug(1, "* $usernick $matches[2]");
            }
        } else {
            debug(1, "<$usernick> $message");
        }

        $mparts[0] = strtolower($mparts[0]);

        if (isset($channels[$to])) {
            $sendto = $to;
        } else {
            $sendto = $usernick;
        }

        if (isset($commands[$mparts[0]])) {

            if (!function_exists($commands[$mparts[0]]['function'])) {
                debug(0, "Internal: Handler {$commands[$mparts[0]]['function']}() doesn't exist!");
                privmsg($sendto, "Bot bug! The requested command doesn't have a corresponding handler!");
                continue;
            }

            if (match_mask($hostmask) < $commands[$mparts[0]]['level']) {
                notice($usernick, "You don't have permission to execute that command.");
                continue;
            }

            $exec = $commands[$mparts[0]]['function'];

            debug(2, "Internal: User $user validated for command $mparts[0]. Executing $exec.");

            $postaction = $exec(array(  'parts' => $mparts,
                                        'from' => $usernick,
                                        'to' => $to,
                                        'reply-to' => $sendto,
                                        'hostmask' => $hostmask,
                                        'message' => $message
                          ) );

            if ($postaction) {
                continue;
            } else {
                break;
            }

        }

    }

}

debug(0, "All done. Closing connection...");

@fclose($irc);

debug(0, "Saving data...");

save_data();

debug(0, "Good bye!");

//------------------------------
// !action handlers
// return 1 for continue;
// return 0 to close connection
// all handlers take $data as
// an argument
//------------------------------

function joinchannel($data) {
    global $channels;

    debug(1, "Joining {$data['parts'][1]}...");

    if (isset($channels[$data['parts'][1]])) {
        notice($data['from'], "I am already in {$data['parts'][1]}.");
    } else {
        send("JOIN {$data['parts'][1]}");
        $channels[$data['parts'][1]] = array();
    }

    return 1;
}

function partchannel($data) {
    global $channels;

    if (isset($channels[$data['to']]) and !isset($data['parts'][1])) {
        $data['parts'][1] = $data['to'];
    }

    debug(1, "Leaving {$data['parts'][1]}...");

    if (!isset($channels[$data['parts'][1]])) {
        notice($data['from'], "I'm not in {$data['parts'][1]}.");
    } else {
        notice($data['from'], "Leaving {$data['parts'][1]}...");
        send("PART {$data['parts'][1]}");

        unset($channels[$data['parts'][1]]);
    }

    return 1;
}

function kill($data) {
    send("QUIT :$data[from] shot me! X-O (" . VERSION_STRING . ")");

    return 0;
}

function say($data) {
    global $channels;

    list($comm, $sendto, $sendmessage) = explode(' ', $data['message'], 3);

    privmsg($sendto, $sendmessage);

    return 1;
}

function version($data) {
    privmsg($data['reply-to'], "I'm running " . VERSION_STRING);

    return 1;
}

function help($data) {
    global $help;

    if (isset($data['parts'][1]) and isset($help[$data['parts'][1]])) {
        notice($data['from'], $help[$data['parts'][1]]);
    } else {
        notice($data['from'], 'Available commands:');

        $list = '';
        foreach ($help as $command => $message) {
            $list .= "$command ";
        }

        notice($data['from'], $list);
        notice($data['from'], 'Use !help <command> for more information.');
    }

    return 1;
}

function kick($data) {
    global $channels;

    debug(1, "Kicking {$data['parts'][1]}...");

    list($command, $user, $reason) = explode(' ', $data['message'], 3);

    if (!$reason) $reason = 'Kick!';

    if (isset($channels[$data['to']])) {
        send("MODE {$data['to']} -o {$data['parts'][1]}");     // just in case
        send("KICK {$data['to']} {$data['parts'][1]} :$reason");
    } else {
        notice($data['from'], "$data[to] is not a channel. Either that, or you're not on it.");
    }

    return 1;
}

function ban_nick($data) {
    global $channels;

    $nick = $data['parts'][1];

    if (!isset($channels[$data['to']])) {
        notice($data['from'], "$data[to] isn't a channel. Either that, or you're not in it.");

        return 1;
    }

    notice($data['from'], "Looking up hostmask for $nick. All operations suspended.");
    $info = gethostbynick($data['parts'][1]);

    if (strpos($info[0], '~') == 1) {
        $info[0] = substr($info[0], 1);
    }

    // generalize it a little bit by taking away the first part of the mask
    // i.e. *.dyn.optonline.net
    $host = '*' . substr($info[1], strpos($info[1], '.'));

    $banhost = "*!*$info[0]@$host";

    notice($data['from'], "OK, kicking and banning.");

    debug(1, "Kicking and banning $nick.");
    send("KICK $data[to] $nick");
    send("MODE $data[to] +b $banhost");

    return 1;
}

function raw($data) {
    list($command, $stuff) = explode(' ', $data['message'], 2);

    debug(1, "Sending raw data: $stuff");

    send($stuff);

    if (strtolower($data['parts'][1]) == 'quit') {
        return 0;
    } else {
        return 1;
    }
}

function mode($data) {
    global $channels;

    debug(2, "Mode change requested by $data[from].");

    if (isset($channels[$data['parts'][1]])) {
        $where = $data['parts'][1];
    } else {
        $where = $data['to'];
    }

    $command = $data['parts'][0];

    // lots of messiness!
    if ($command == '!op' or $command == '!opme') {
        $what = ' +o';
    } elseif ($command == '!deop') {
        $what = ' -o';
    } elseif ($command == '!voice') {
        $what = ' +v';
    } elseif ($command == '!devoice') {
        $what = ' -v';
    } elseif ($command == '!moderate' or $command == '!mod') {
        $what = ' +m';
    } elseif ($command == '!unmoderate' or $command == '!unmod') {
        $what = ' -m';
    } elseif ($command == '!ban') {
        $what = ' +b';
    } elseif ($command == '!unban') {
        $what = ' -b';
    } elseif ($command == '!mode') {
        $what = ' ' . $data['parts'][2];
    }

    if (isset($data['parts'][2])) {
        $who = ' ' . $data['parts'][2];
    } elseif (isset($data['parts'][1]) and !isset($channels[$data['parts'][1]])) {
        $who = ' ' . $data['parts'][1];
    } elseif ($command == '!moderate' or $command == '!mod' or $command == '!unmoderate' or $command == '!unmod') {
        $who = '';
    } else {
        $who = ' ' . $data['from'];
    }

    debug(1, "Setting mode in $where to \"" . substr($what, 1) . "$who\".");

    send("MODE $where$what$who");

    return 1;
}

function bashit($data) {
    if (!isset($data['parts'][1])) {
        $data['parts'][1] = 'random1';
    }

    debug(1, "Internal: Opening http://www.bash.org/?" . $data['parts'][1]);

    $fp = @fopen('http://www.bash.org/?' . $data['parts'][1], 'r');

    if (!$fp) {
        notice($data['from'], "Couldn't open: http://www.bash.org/?{$data['parts'][1]}");
    } else {
        $bash = '';
        while ($chunk = fread($fp, 4096)) $bash .= $chunk;
        fclose($fp);

        if (preg_match_all('!<a href="\?(\d+)" title="permanent link to this quote.">!i', $bash, $matches, PREG_SET_ORDER)) {
            $quotenum = count($matches);
    
            debug(1, "Internal: Found $quotenum quotes.");

            if ($quotenum == 1 or $data['parts'][1] == 'random' or $data['parts'][1] == 'random1' or !isset($data['parts'][1])) {
                if (preg_match('!<p class="qt">(.+)</p>!Uis', $bash, $match)) {
                    if (preg_match('!>\(([\-\d]+)\)<!U', $bash, $score)) {
                        $score = $score[1];
                    } else {
                        $score = 'unknown';
                    }

                    notice($data['from'], "Your quote, sire (#{$matches[0][1]}, score: $score):");

                    $qlines = explode('<br />', $match[1]);
                    for ($i = 0; $i < count($qlines); $i++) {
                        notice($data['from'], html_entity_decode(trim($qlines[$i])));
                    }
                } else {
                    notice($data['from'], "It said there were quotes, but it lied! LIED!");
                }
            } else {
                notice($data['from'], "The following quotes were found:");

                $quotes = '';
                for ($i = 0; $i < count($matches); $i++) {
                    $quotes .= "{$matches[$i][1]} ";
                }

                notice($data['from'], $quotes);
                notice($data['from'], "Use !bash num to view a quote");
            }
        } else {
            notice($data['from'], "Couldn't find any quotes!");
        }
    }

    return 1;
}

function google($data) {
    return 1;
}

function trillian_forums($data) {
    $server = isset($data['parts'][3]) && trim($data['parts'][3]) != '' ? $data['parts'][3] : 'www.ceruleanstudios.com';
    $forumid = isset($data['parts'][2]) && trim($data['parts'][2]) != '' ? $data['parts'][2] : '28';
    (integer)$limit = isset($data['parts'][1]) && trim($data['parts'][1]) != '' ? $data['parts'][1] : 10;

    $url = "http://$server/forums/forumdisplay.php?forumid=$forumid";

    $fp = @fopen($url, 'r');

    if (!$fp) {
        notice($data['from'], "Couldn't open connection to $server to retrieve $url. You can try an alternate server using {$data['parts'][0]} <limit> <forumid> <servername>");

        return 1;
    }

    $page = '';
    while ($chunk = fread($fp, 4096)) $page .= $chunk;

    preg_match_all("#<a href=\"showthread\.php\?(?:s=[a-fA-F0-9]*&)?threadid=(\d+)\">(.+)</a>#Ui", $page, $matches, PREG_SET_ORDER);

    notice($data['from'], "Last $limit threads in forum $forumid:");

    $shown = 0;
    for ($i = 0; $i < count($matches); $i++) {
        if (not_sticky($matches[$i][1]) and $shown <= $limit) {
            $title = clean_title($matches[$i][2]);
            $threadid = $matches[$i][1];

            notice($data['from'], "$title: http://$server/forums/showthread.php?threadid=$threadid");

            $shown++;
        }
    }

    return 1;
}

function add_sticky($data) {
    global $stickies, $db;

    if (!isset($data['parts'][1])) {
        notice($data['from'], "No threadid specified.");

        return 1;
    }

    $db->query("insert into forum_stickies values ({$data['parts'][1]})");

    $stickies[] = $data['parts'][1];

    notice($data['from'], "Thread {$data['parts'][1]} will no longer be displayed.");

    return 1;
}

function change_access($data) {
    global $users, $ids, $db;

    if (!isset($data['parts'][1]) or !isset($data['parts'][2])) {
        notice($data['from'], "Missing arguments.");

        return 1;
    }

    Debug(1, "Internal: Changing access of {$data['parts'][1]}...");

    $db->query("update bot_users set level = {$data['parts'][2]} where user = \"{$data[parts][1]}\"");

    $users[$data['parts'][1]]['level'] = array($data['parts'][2]);

    debug(1, "Internal: Reseting cached access level for {$data['parts'][1]}...");
    list($user, $host) = gethostbynick($data['parts'][1]);
    $hostmask = "$user@$host";

    $ids[$hostmask] = $data['parts'][2];

    notice($data['from'], "Access level for {$data['parts'][1]} changed to {$data['parts'][2]}.");
    notice($data['parts'][1], "Your access level has been changed to {$data['parts'][2]}.");

    return 1;
}

function ident($data) {
    global $ids, $users, $db;

    debug(1, "Internal: Ident requested for user $data[from], password {$data['parts'][1]}.");

    if (!isset($data['parts'][1])) {
        notice($data['reply-to'], "No password specified.");

        return 1;
    }

    if (isset($users[$data['from']])) {
        if ($users[$data['from']]['password'] == md5($data['parts'][1])) {
            $ids[$data['hostmask']] = $users[$data['from']]['level'];

            notice($data['from'], "User $data[from] ($data[hostmask]) identified for level {$users[$data['from']]['level']}.");
            debug(1, "Internal: $data[from] ($data[hostmask]) identified for level $user[level].");
        } else {
            notice($data['from'], "Invalid password.");
        }

        return 1;
    }

    $user = $db->first_row("select * from bot_users where user = \"$data[from]\"");

    if (!$user) {
        notice($data['from'], "User $data[from] not found, creating new user.");
        $db->query("insert into bot_users (user, password, level) values (\"$data[from]\", md5(\"{$data['parts'][1]}\"), 2)");
        $user = $db->first_row("select * from bot_users where user = \"$data[from]\"");
    }

    if (md5($data['parts'][1]) == $user['password']) {
        $ids[$data['hostmask']] = $user['level'];

        notice($data['from'], "User $data[from] ($data[hostmask]) identified for level $user[level].");
        debug(1,  "Internal: $data[from] ($data[hostmask]) identified for level $user[level].");

        $users[$data['from']] = $user;
    } else {
        notice($data['from'], "Invalid password.");
    }

    return 1;
}

function unident($data) {
    global $ids;

    if (!isset($ids[$data['hostmask']])) {
        notice($data['from'], "You haven't identified yet. So, uh, you can't exactly unidentify.");

        return 1;
    }

    unset($ids[$data['hostmask']]);

    return 1;
}

function inline_handler($data) {
    global $commands;

    $command = $data['parts'][1];
    $function = $data['parts'][2];
    $level = $data['parts'][3];

    debug(1, "Adding temporary command $command with handler $function() and access level of $level.");

    $commands[$command] = array('function' => $function, 'level' => (integer)$level);

    notice($data['from'], "Temporary alias $command added with handler $function() and access level $level.");

    return 1;
}

function new_handler($data) {
    list($command, $name, $stuff) = explode(' ', $data['message'], 3);

    debug(1, "Defining function $name with the following actions: $stuff.");
    eval("function $name(\$data) \{ $stuff }");

    notice($data['from'], "Function $name successfully defined. You can now assign a handler using !handler <alias> <function> <level>");

    return 1;
}

function restart_bot($data = null) {
    global $filename, $irc, $restartwithconfig;

    debug(1, "Restarting bot...");

    if ($data != null) {
        privmsg($data['reply-to'], 'Restarting myself...');
        send("QUIT :Restarting");

        @fclose($irc);

        save_data();

        if (isset($data['parts'][1])) {
            $restartwithconfig = $data['parts'][1];
        }
    }

    if ($restartwithconfig != 0) {
        $conf = "-config $restartwithconfig";
    } else {
        $conf = "-noconfig";
    }

    debug(1, "Restarting with config options: $conf");
    debug(1, "Good bye!");

    echo "\n\n\n---\n\n\n";

    system("php $filename $conf");
    exit;
}

//------------------------------
// Functions
//------------------------------

function read_incoming($length = 512) {
    global $irc;

    $line = trim(fgets($irc, $length));

    debug(2, "Received: $line");

    return $line;

}

function send($message) {
    global $irc;

    debug(2, "Sending: $message");

    fputs($irc, "$message\r\n");
}

function privmsg($who, $message) {
    global $nick;

    send("PRIVMSG $who :$message");
    debug(1, "<$nick> $message");
}

function notice($who, $message) {
    send("NOTICE $who :$message");
    debug(1, "-{$who}- $message");
}

function load_user_info() {
    global $db, $users, $verbose;

    $result = $db->query("select * from bot_users");

    debug(1, "Internal: Loading registered users...");

    while ($user = $db->fetch_array($result)) {
        debug(2, "Loading user $user[user], level $user[level], password $user[password]...");

        $users[$user['user']] = array('level' => $user['level'], 'password' => $user['password']);
    }
}

function gethostbynick($nick) {
    global $verbose;

    debug(2, "Internal: Getting WHOIS information for $nick.");

    send("WHOIS $nick");
    while ($in = read_incoming()) {
        $stuff = explode(' ', $in);

        if ($stuff[1] == '311') {
            debug(2, "Internal: Got WHOIS information, returning user $stuff[4] and host $stuff[5].");

            return array($stuff[4], $stuff[5]);
        }
    }
}

function match_mask($hostmask) {
    global $ids;

    if (isset($ids[$hostmask])) {
        return $ids[$hostmask];
    }

    $ids[$hostmask] = 1;

    return 1;
}

function save_data() {

    // either save to file or database... not sure yet.

}

function not_sticky($threadid) {
    global $stickies;

    if (!is_array($stickies)) {
        load_forum_stickies();
    }

    for ($i = 0; $i < count($stickies); $i++) {
        if ($threadid == $stickies[$i]) {
            return 0;
        }
    }

    return 1;
}

function load_forum_stickies() {
    global $db, $stickies;

    $result = $db->query('select * from forum_stickies');

    while (list($id) = $db->fetch_array($result)) {
        $stickies[] = $id;
    }
}

function clean_title($title) {
    $title = str_replace('&quot;', '"', $title);
    $title = str_replace('&gt;', '>', $title);
    $title = str_replace('&lt;', '<', $title);

    return $title;
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

//------------------------------
// The Configurator!
//------------------------------

function readin($length = 256) {
    if (!defined(STDIN)) {
        define(STDIN, fopen('php://stdin', 'r'));
    }

    return trim(fgets(STDIN, $length));
}

function configurator() {
    global $configversion;

    echo "Continue with default configuration? [y/n] ";
    $choice = readin();

    if (strtolower($choice) == 'y') {
        return 'default.conf';
    } else {

        // configuromaticaness!
        echo   "Welcome to the DanBot configurator! This step by step wizard will setup your bot "
              ."just the way you want it. If you would like to keep a default setting, hit ENTER, "
              ."without typing anything. Here we go...";

        echo "\n\nWould you like to load a configuration file? If not, hit enter. [] ";
        $configfile = readin();

        if ($configfile) {
            return $configfile;
        }

        global $server;
        echo "\nWhich server would you like to connect to? [$server] ";
        $nserv = readin();

        if ($nserv) $server = $nserv;

        global $port;
        echo "\nWhich port should I connect to $server on? [$port] ";
        $nport = readin();

        if ($nport) $port = $nport;

        global $nick;
        echo "\nWhat would you like the bots nickname to be? Keep in mind that some servers require "
              ."a registered nickname, in which case the nickname should be registered before using "
              ."the bot. So, nickname? [$nick] ";
        $nnick = readin();

        if ($nnick) $nick = $nnick;

        global $nickpass;
        echo "\nSome servers require that you have a registered nickname in order to perform certain operations. "
              ."If your bot's nickname is registered, enter the password now, or just hit enter to continue without "
              ."identifying. [] ";
        $npass = readin();

        if ($npass) $nickpass = $npass;

        global $user;
        echo "\nWhat would you like your bot's username to be? It shows up in its hostmask as $nick!<username>@your.host. [$user] ";
        $nuser = readin();

        if ($nuser) $user = $nuser;

        global $info;
        echo "\nWhat should the bots real name show up like in a whois request? [$info] ";
        $ninfo = readin();

        if ($ninfo) $info = $ninfo;

        global $autojoin;

        $ac = '';
        for ($i = 0; $i < count($autojoin); $i++) {
            $ac .= "$autojoin[$i],";
        }
        $ac = substr($ac, 0, -1);

        echo "\nWhich channels should the bot join on startup? Seperate them with commas, NO SPACES! [$ac] ";
        $nautojoin = readin(512);

        if ($nautojoin) {
            $nautojoin = str_replace(', ', ',', $nautojoin);    // anti-idiot check
            $nautojoin = str_replace(' ,', ',', $nautojoin);
            $autojoin = explode(',', $nautojoin);
        }

        global $verbose;
        echo "\nHow much info about the currently running bot should I show you? 0 = none, 1 = some, 2 = more, 3 = everything [$verbose] ";
        $nverb = readin();

        if ($nverb) $verbose = $nverb;

        global $saveconfig;
        echo "\nDo you want to save these settings for later use? If yes, enter a filename, otherwise just hit enter. [] ";
        $configfile = readin();

        if ($configfile) $saveconfig = $configfile;

        if ($saveconfig) {
            $fp = @fopen($saveconfig, 'w');
            if (!$fp) {
                echo "\nCouldn't open $saveconfig, configuration won't be saved.";
            } else {
                $ac = '';
                for ($i = 0; $i < count($autojoin); $i++) {
                    $ac .= "\"$autojoin[$i]\",";
                }
                $ac = substr($ac, 0, -1);

                $config = "<"."? /* $configversion */

\$server = \"$server\";
\$port = $port;
\$nick = \"$nick\";
\$nickpass = \"$nickpass\";
\$user = \"$user\";
\$info = \"$info\";
\$verbose = $verbose;
\$autojoin = array($ac);

?".">";

                fwrite($fp, $config);
                fclose($fp);
            }

            echo "\nDo you want to use this configuration as your default every time you start? [y/n] ";
            $choice = readin();

            if ($choice == 'y') {
                if (!@copy($saveconfig, 'default.conf')) {
                    echo "\nCopy of $saveconfig to default.conf failed!\n";
                } else {
                    echo "\nOK, $saveconfig was copied to default.conf.\n";
                }
            }
        }

        echo "\n\nYou're all set! Everything's configured and, if you chose to, saved. Your bot will now run. Press enter to continue.";
        $blank = readin();
    }

    return 0;

}


?>
