<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Exception;

class UpdateController extends Controller
{
    public function generate_token()
    {
        $groups = Group::where('parent_id', '=', null)->get();

        foreach ($groups as $group) {
            $accountId = $group->account_id;
            $clientId = $group->client_id;
            $clientSecret = $group->client_secret;

            $oauthUrl = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . $accountId;
            try {
                $authHeader = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);
                $ch = curl_init($oauthUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: ' . $authHeader));
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode == 200) {
                    $parent = Group::find($group->id);
                    $parent->api = json_decode($response)->access_token;
                    $parent->save();
                    foreach ($group->children as $child) {
                        $get = Group::find($child->id);
                        $get->api = json_decode($response)->access_token;
                        $get->save();
                    }
                } else return redirect()->back()->with('error', 'API калитлар билан боғлиқ қандайдир муаммолар мавжуд!');
            } catch (Exception $e) {
                return redirect()->back()->with('error', 'API калитларни янгилаб бўлмади!');
            }
        }
        return redirect('/home')->with('success', 'Тизим калитлари янгиланди!');
    }
}
