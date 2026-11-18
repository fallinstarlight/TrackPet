<?php
/* Set of functions for handling user authentication and session management */
namespace TrackPet\utils;

class Auth
{
    private static $timeout = 1800; 

    /* Start the session and check for timeout */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            if (!session_start()) throw new \RuntimeException('Unable to start session');
        }

        self::checkTimeout();
    }

    /* Check if the session has timed out, only 30 minutes of inactivity before shutting down the session */
    private static function checkTimeout()
    {
        if (isset($_SESSION['LAST_ACTIVITY'])) {
            $inactiveTime = time() - $_SESSION['LAST_ACTIVITY'];

            if ($inactiveTime > self::$timeout) {
                self::destroy();
                self::start();
                self::regenerate();
                return false;
            }
        }

        $_SESSION['LAST_ACTIVITY'] = time();
        return true;
    }

    /* Regenerate the session ID to prevent session fixation attacks */
    public static function regenerate()
    {
        session_regenerate_id(true);
    }

    /* Destroy the session */
    public static function destroy()
    {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function isLoggedIn()
    {
        return isset($_SESSION['userId']);
    }

    public static function requireLogIn(){
        if(!self::isLoggedIn()){
            http_response_code(401);
            die(json_encode(["status"=> "error", "message" => "Log in is needed to access this resource", "data" => []]));
        }
    }
}

Auth::start();
