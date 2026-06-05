<?php namespace KnightSwarm\LaravelSaml\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use KnightSwarm\LaravelSaml\Account;

class SamlController extends Controller {

    private $account;

    public function __construct(Account $act)
    {
        $this->account = $act;
    }

    public function login()
    {
        if (Input::has('url')) {
            $url = Input::get('url');
            if (!preg_match("~^(//|[^/]+:)~", $url)) {
                Session::flash('url.intended', $url);
            }
        }

        if (!$this->account->samlLogged()) {
            Auth::logout();
            $this->account->samlLogin();
        }

        if ($this->account->samlLogged()) {
            $id = $this->account->getSamlUniqueIdentifier();
            if (!$this->account->IdExists($id)) {
                if (Config::get('laravel-saml.can_create', true)) {
                    $this->account->createUser();
                } else {
                    return Response::make(Config::get('laravel-saml.can_create_error'), 400);
                }
            } else {
                if (!$this->account->laravelLogged()) {
                    $this->account->laravelLogin($id);
                }
            }
        }

        if ($this->account->samlLogged() && $this->account->laravelLogged()) {
            $intended = Session::get('url.intended');
            $intended = str_replace(Config::get('app.url'), '', $intended);
            Session::flash('url.intended', $intended);
            return Redirect::intended('/');
        }
    }

    public function logout()
    {
        $auth_cookie = $this->account->logout();
        return Redirect::to(Config::get('laravel-saml.logout_target', 'http://'.$_SERVER['SERVER_NAME']))->withCookie($auth_cookie);
    }
}