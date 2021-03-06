<?php
/*
enum(array( // AcctError
    'ACCT_USERNAME_LENGTH'      => 'activate_usernamelength',
    'ACCT_PASSWORD_LENGTH'      => 'activate_passwordlength',
    'ACCT_USERNAME_SYMBOLS'     => 'activate_invalidusername',
    'ACCT_PASSWORD_SYMBOLS'     => 'activate_invalidpassword',
    'ACCT_EMAIL_SYMBOLS'        => 'signup_emailinvalid',

    'ACCT_PASSWORDS_NOT_EQUAL'  => 'signup_passwordsnotequal',
    'ACCT_USERNAME_EXISTS'      => 'activate_usernameinuse',
    'ACCT_NO_SUCH_ACCT'         => 'signin_un_or_pass_fail',
    'ACCT_IP_LOCKED'            => 'signin_ip_locked',

    'ACCT_SIGNUP_BLOCKED'       => 'signup_blocked',
    'ACCT_SIGNIN_BLOCKED'       => 'signin_blocked',

    'ACCT_INTERNAL_ERROR'       => 'internal_error',
));

enum(array( // UserPropsLimits
    'USERNAME_LENGTH_MIN'    => 4,
    'USERNAME_LENGTH_MAX'    => 16,
    'PASSWORD_LENGTH_MIN'    => 6,
    'PASSWORD_LENGTH_MAX'    => 16,
));
*/

function signin()
{
    if (!isset($_POST['username']) || !isset($_POST['password']))
        return Lang::$account['userNotFound'];

    $username = $_POST['username'];
    $password = $_POST['password'];
    $remember = $_POST['remember_me'] == 'yes';

    // handle login try limitation
    $ipBan = DB::Aowow()->selectRow('SELECT ip, count, UNIX_TIMESTAMP(unbanDate) as unbanDate FROM ?_account_bannedIPs WHERE type = 0 AND ip = ?',
        $_SERVER['REMOTE_ADDR']
    );

    if (!$ipBan)                                        // no entry exists; set count to 1
        DB::Aowow()->query('INSERT INTO ?_account_bannedIPs VALUES (?, 0, 1, FROM_UNIXTIME(?))',
            $_SERVER['REMOTE_ADDR'],
            time() + CFG_FAILED_AUTH_EXCLUSION
        );
    else if ($ipBan['unbanDate'] < time())              // ip has accumulated counts but time expired; reset count to 1
        DB::Aowow()->query('INSERT IGNORE INTO ?_account_bannedIPs VALUES (?, 0, 1, ?)',
            $_SERVER['REMOTE_ADDR'],
            time() + CFG_FAILED_AUTH_EXCLUSION
        );
    else                                                // entry already exists; increment count
        DB::Aowow()->query('UPDATE ?_account_bannedIPs SET count = count + 1, unbanDate = FROM_UNIXTIME(?) WHERE ip = ?',
            time() + CFG_FAILED_AUTH_EXCLUSION,
            $_SERVER['REMOTE_ADDR']
        );

    $id = DB::Aowow()->SelectCell('SELECT id FROM ?_account WHERE user = ?', $username);
    if (!$id)
        return Lang::$account['userNotFound'];

    User::init($id);

    switch (User::Auth($password))
    {
        case AUTH_OK:
            DB::Aowow()->query('DELETE FROM ?_account_bannedIPs WHERE type = 0 AND ip = ?',
                $_SERVER['REMOTE_ADDR']
            );
            DB::Aowow()->query('UPDATE ?_account SET lastLogin = FROM_UNIXTIME(?), timeout = FROM_UNIXTIME(?) WHERE id = ?',
                time(),
                $remember ?  0 : time() + CFG_SESSION_TIMEOUT_DELAY,
                $id
            );
            User::writeCookie();                    // overwrites the current user
            return;
        case AUTH_BANNED:
           User::writeCookie();
           return Lang::$account['userBanned'];
        case AUTH_WRONGPASS:
            User::destroy();
            return Lang::$account['wrongPass'];
        case AUTH_IPBANNED:
            User::destroy();
            return sprintf(Lang::$account['loginsExceeded'], round(CFG_FAILED_AUTH_EXCLUSION / 60));
        default:
            return;
    }
}

function signup()
{
    global $smarty;

/*
        $username = Get(GET_STRING, 'username', 'POST');
        $password = Get(GET_STRING, 'password', 'POST');
        $pwd2     = Get(GET_STRING, 'password2', 'POST');
        $email    = Get(GET_STRING, 'email', 'POST');
        $remember = Get(GET_BOOL, 'remember_me', 'POST');

        if($password != $pwd2)
        {
            $this->acct_error = ACCT_PASSWORDS_NOT_EQUAL;
            $this->type = 'signup';
            return;
        }

        // Check length
        if(strlen($username) > USERNAME_LENGTH_MAX || strlen($username) < USERNAME_LENGTH_MIN)
        {
            $this->acct_error = ACCT_USERNAME_LENGTH;
            $this->type = 'signup';
            return;
        }
        if(strlen($password) > PASSWORD_LENGTH_MAX || strlen($password) < PASSWORD_LENGTH_MIN)
        {
            $this->acct_error = ACCT_PASSWORD_LENGTH;
            $this->type = 'signup';
            return;
        }

        // Check symbols
        if(preg_match('/[^\w\d]/i', $username))
        {
            $this->acct_error = ACCT_USERNAME_SYMBOLS;
            $this->type = 'signup';
            return;
        }
        if(preg_match('/[^\w\d!"#\$%]/', $password))
        {
            $this->acct_error = ACCT_PASSWORD_SYMBOLS;
            $this->type = 'signup';
            return;
        }
        if(!preg_match('/^([a-z0-9._-]+)(\+[a-z0-9._-]+)?(@[a-z0-9.-]+\.[a-z]{2,4})$/i', $email))
        {
            $this->acct_error = ACCT_EMAIL_SYMBOLS;
            $this->type = 'signup';
            return;
        }

        // After 5 signup tries in a row,
        // or after a single successful signup,
        // the signup feature is blocked for 3 min
        // and the time is expanded to full-time block.

        DB::Realm()->Query('DELETE FROM account_ip_signup WHERE ip = ? AND time <= ?d', $_SERVER['REMOTE_ADDR'], time() - 3*MINUTE);
        DB::Realm()->Query('INSERT IGNORE INTO account_ip_signup (ip,time,tries) VALUES (?,?d,?d)', $_SERVER['REMOTE_ADDR'], time(), 0);
        $tries = DB::Realm()->SelectCell('SELECT tries FROM account_ip_signup WHERE ip = ?', $_SERVER['REMOTE_ADDR']);
        if($tries >= 5)
        {
            DB::Realm()->Query('UPDATE account_ip_signup SET time = ?d WHERE ip = ?', time(), $_SERVER['REMOTE_ADDR']);
            $this->acct_error = ACCT_SIGNUP_BLOCKED;
            $this->type = 'signup';
            return;
        }
        DB::Realm()->Query('UPDATE account_ip_signup SET tries = tries + 1 WHERE ip = ?', $_SERVER['REMOTE_ADDR']);

        $result = DB::Realm()->SelectCell('SELECT 1 FROM account WHERE username = ?', $username);
        if($result)
        {
            $this->acct_error = ACCT_USERNAME_EXISTS;
            $this->type = 'signup';
            return;
        }

        DB::Realm()->Query('UPDATE account_ip_signup SET tries = tries + 5 WHERE ip = ?', $_SERVER['REMOTE_ADDR']);
        $id = DB::Realm()->Query('
                INSERT INTO account (username,sha_pass_hash,email,joindate,expansion,last_ip)
                VALUES (?,?,?,NOW(),?d,?)
            ',
            strtoupper($username),
            $hash = AccountPage::CreateHash($username, $password),
            strtolower($email),
            2,
            $_SERVER['REMOTE_ADDR']
        );
        if($id)
        {
            DB::Realm()->Query('UPDATE account_ip_signup SET tries = tries + 5 WHERE ip = ?', $_SERVER['REMOTE_ADDR']);
            DB::Realm()->Query('INSERT INTO account_aowow_extend (id,name) VALUES (?d,?)', $id, 'user-'.wn_create($id, WN_));

            $us = new User($id);
            if($us->Auth($hash) == AUTH_OK)
                $us->SetAuthCookies($remember);
            else
            {
                $this->acct_error = ACCT_INTERNAL_ERROR;
                $this->type = 'signin';
                return;
            }
        }
        else
        {
            $this->acct_error = ACCT_INTERNAL_ERROR;
            $this->type = 'signup';
            return;
        }
*/

    // Account creation
    if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['c_password']) && CFG_ALLOW_REGISTER)
    {
        // password mismatch
        if ($_POST['password'] != $_POST['c_password'])
            $smarty->assign('signup_error', Lang::$account['passMismatch']);
        else
        {
            // AccName already in use
            if (DB::Aowow()->selectCell('SELECT 1 FROM aowow_account WHERE user = ? LIMIT 1', $_POST['username']))
                $smarty->assign('signup_error', Lang::$account['nameInUse']);
            else
            {
                $success = DB::Aowow()->query('INSERT INTO aowow_account (user, passHash, displayName, email, joindate, lastIP, locale) VALUES (?, ?, ?, ?, NOW(), ?, ?)',
                    $_POST['username'],
                    sha1(strtoupper($_POST['username']).':'.strtoupper($_POST['password'])),
                    Util::ucFirst($_POST['username']),
                    (isset($_POST['email']))? $_POST['email'] : '',
                    (isset($_SERVER["REMOTE_ADDR"]))? $_SERVER["REMOTE_ADDR"] : '',
                    0
                );
                if ($success > 0)
                    // all fine, send to login
                    $_REQUEST['account']='signin';
                else
                    // something went wrong
                    $smarty->assign('signup_error', Lang::$account['unkError']);
            }
        }
    }
}

function dashboard()
{
    // cpmsg    change pass messaeg class:failure|success, msg:blabla
}

function recoverPass()
{
}

function recoverUser()
{
}

$smarty->updatePageVars(array(
    'reqCSS' => array(
        ['path' => STATIC_URL.'/css/Profiler.css'],
    ),
    'reqJS'  => array(
        STATIC_URL.'/js/user.js',
        STATIC_URL.'/js/profile.js',
    ),
));

$smarty->assign('lang', array_merge(Lang::$main, Lang::$account, ['colon' => Lang::$colon]));

if (User::$id)
{
    switch ($pageParam)
    {
        case 'exclude':
            // profiler completion exclude handler
            // $_POST['groups'] = bitMask of excludeGroupIds when using .. excludeGroups .. duh
            // should probably occur in g_user.excludegroups (dont forget to also set g_users.settings = {})
            die();
            break;
        case 'signout':
            User::destroy();
            $next = explode('?', $_SERVER['HTTP_REFERER']);
            $next = !empty($next[1]) ? '?'.$next[1] : '.';
            header('Location: '.$next);
        case 'weightscales':
            if (isset($_POST['save']))
            {
                if (!isset($_POST['id']))
                {
                    $res = DB::Aowow()->selectRow('SELECT max(id) as max, count(id) as num FROM ?_account_weightscales WHERE account = ?d', User::$id);
                    if ($res['num'] < 5)                    // more or less hard-defined in LANG.message_weightscalesaveerror
                        $_POST['id'] = ++$res['max'];
                    else
                        die('0');
                }

                if (DB::Aowow()->query('REPLACE INTO ?_account_weightscales VALUES (?d, ?d, ?, ?)', intVal($_POST['id']), User::$id, $_POST['name'], $_POST['scale']))
                    die((string)$_POST['id']);
                else
                    die('0');
            }
            else if (isset($_POST['delete']) && isset($_POST['id']) && User::$id)
                DB::Aowow()->query('DELETE FROM ?_account_weightscales WHERE id = ?d AND account = ?d', intVal($_POST['id']), User::$id);
            else
                die('0');

            break;
        case '';
            dashboard();
            $smarty->display('dashboard.tpl');
            break;
        default:
            $smarty->error();
    }
}
else
{
    switch ($pageParam)
    {
        case 'signin_do':
            $error = signin();
            if ($error)
                $smarty->assign('signinError', $error);
            else
                header('Location: '.$_GET['next']);
        case 'signin':
            if (!isset($_GET['next']))
            {
                $next = isset($_SERVER['HTTP_REFERER']) ? explode('?', $_SERVER['HTTP_REFERER']) : '.';
                $smarty->assign('next', isset($next[1]) ? '?'.$next[1] : '.');
            }
            else
                $smarty->assign('next', $_GET['next']);

            $smarty->assign('register', CFG_ALLOW_REGISTER);
            $smarty->display('signin.tpl');
            break;
        case 'signup_do':
            $error = signup();
            if ($error)
                $smarty->assign('signupError', $error);
            else
                header('Location: '.$_GET['next']);
            break;
        case 'signup':
            if (!isset($_GET['next']))
            {
                $next = isset($_SERVER['HTTP_REFERER']) ? explode('?', $_SERVER['HTTP_REFERER']) : '.';
                $smarty->assign('next', isset($next[1]) ? '?'.$next[1] : '.');
            }
            else
                $smarty->assign('next', $_GET['next']);
            $smarty->display('signup.tpl');
            break;
        case 'forgotpassword':
            recoverPass();
            $smarty->display('recoverPass.tpl');
            break;
        case 'forgotusername':
            recoverUser();
            $smarty->display('recoverUser.tpl');
            break;
        default:
            header('Location: '.($_GET['next'] ? $_GET['next'] : '.'));
            break;
    }
}

?>
