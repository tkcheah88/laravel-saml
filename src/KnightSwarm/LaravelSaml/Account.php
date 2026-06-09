<?php namespace KnightSwarm\LaravelSaml;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;

class Account
{
    protected function getUserIdProperty()
    {
        return Config::get("laravel-saml.internal_id_property", "email");
    }

    protected function getSamlIdProperty()
    {
        return Config::get("laravel-saml.saml_id_property", "email");
    }

    protected function getUserModel()
    {
        return Config::get("saml.sp_user_model_class") ?:
            Config::get("laravel-saml.sp_user_model_class") ?:
            Config::get("auth.providers.users.model") ?:
            "\App\User";
    }

    /**
     * Check if the id exists in the specified user property.
     * If no property is defined default to 'email'.
     */
    public function IdExists($id)
    {
        $property = $this->getUserIdProperty();
        $user = $this->getUserModel()::where($property, "=", $id)->count();
        return $user === 0 ? false : true;
    }

    public function samlLogged()
    {
        return \Saml::isAuthenticated();
    }

    public function samlLogin()
    {
        \Saml::requireAuth();
    }

    public function laravelLogin($id)
    {
        if ($this->IdExists($id)) {
            $property = $this->getUserIdProperty();
            $userid = (int) $this->getUserModel()
                ::where($property, "=", $id)
                ->take(1)
                ->get()[0]->id;
            Auth::login($this->getUserModel()::find($userid));
        }
    }

    public function getSamlAttribute($attribute)
    {
        $data = \Saml::getAttributes();
        return $data[$attribute][0];
    }

    public function getSamlUniqueIdentifier()
    {
        return $this->getSamlAttribute($this->getSamlIdProperty());
    }

    public function getSamlName()
    {
        return $data["SAML_FIRST_NAME"][0] . " " . $data["SAML_LAST_NAME"][0];
    }

    public function laravelLogged()
    {
        return Auth::check();
    }

    /**
     * If mapping between saml attributes and object attributes are defined
     * then fill user object with mapped values.
     */
    protected function fillUserDetails($user)
    {
        $mappings = Config::get("laravel-saml.object_mappings", []);
        foreach ($mappings as $key => $mapping) {
            $user->{$key} = $this->getSamlAttribute($mapping);
        }
    }

    public function createUser()
    {
        $user = new $this->getUserModel();
        $user->{$this->getUserIdProperty()} = $this->getSamlUniqueIdentifier();
        $this->fillUserDetails($user);
        $user->save();
        $this->laravelLogin($user->{$this->getUserIdProperty()});
    }

    public function logout()
    {
        Auth::logout();
        $auth_cookie = Cookie::forget("SimpleSAMLAuthToken");
        return $auth_cookie;
    }
}
