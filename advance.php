<?php

namespace Hp;

//  PROJECT HONEY POT ADDRESS DISTRIBUTION SCRIPT
//  For more information visit: http://www.projecthoneypot.org/
//  Copyright (C) 2004-2019, Unspam Technologies, Inc.
//
//  This program is free software; you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation; either version 2 of the License, or
//  (at your option) any later version.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with this program; if not, write to the Free Software
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA
//  02111-1307  USA
//
//  If you choose to modify or redistribute the software, you must
//  completely disconnect it from the Project Honey Pot Service, as
//  specified under the Terms of Service Use. These terms are available
//  here:
//
//  http://www.projecthoneypot.org/terms_of_service_use.php
//
//  The required modification to disconnect the software from the
//  Project Honey Pot Service is explained in the comments below. To find the
//  instructions, search for:  *** DISCONNECT INSTRUCTIONS ***
//
//  Generated On: Wed, 13 Feb 2019 09:42:42 -0500
//  For Domain: www.corax.org
//
//

//  *** DISCONNECT INSTRUCTIONS ***
//
//  You are free to modify or redistribute this software. However, if
//  you do so you must disconnect it from the Project Honey Pot Service.
//  To do this, you must delete the lines of code below located between the
//  *** START CUT HERE *** and *** FINISH CUT HERE *** comments. Under the
//  Terms of Service Use that you agreed to before downloading this software,
//  you may not recreate the deleted lines or modify this software to access
//  or otherwise connect to any Project Honey Pot server.
//
//  *** START CUT HERE ***

define('__REQUEST_HOST', 'hpr6.projecthoneypot.org');
define('__REQUEST_PORT', '80');
define('__REQUEST_SCRIPT', '/cgi/serve.php');

//  *** FINISH CUT HERE ***

interface Response
{
    public function getBody();
    public function getLines(): array;
}

class TextResponse implements Response
{
    private $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function getBody()
    {
        return $this->content;
    }

    public function getLines(): array
    {
        return explode("\n", $this->content);
    }
}

interface HttpClient
{
    public function request(string $method, string $url, array $headers = [], array $data = []): Response;
}

class ScriptClient implements HttpClient
{
    private $proxy;
    private $credentials;

    public function __construct(string $settings)
    {
        $this->readSettings($settings);
    }

    private function getAuthorityComponent(string $authority = null, string $tag = null)
    {
        if(is_null($authority)){
            return null;
        }
        if(!is_null($tag)){
            $authority .= ":$tag";
        }
        return $authority;
    }

    private function readSettings(string $file)
    {
        if(!is_file($file) || !is_readable($file)){
            return;
        }

        $stmts = file($file);

        $settings = array_reduce($stmts, function($c, $stmt){
            list($key, $val) = \array_pad(array_map('trim', explode(':', $stmt)), 2, null);
            $c[$key] = $val;
            return $c;
        }, []);

        $this->proxy       = $this->getAuthorityComponent($settings['proxy_host'], $settings['proxy_port']);
        $this->credentials = $this->getAuthorityComponent($settings['proxy_user'], $settings['proxy_pass']);
    }

    public function request(string $method, string $uri, array $headers = [], array $data = []): Response
    {
        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => $headers + [$this->credentials ? 'Proxy-Authorization: Basic ' . base64_encode($this->credentials) : null],
                'proxy' => $this->proxy,
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        $body = file_get_contents($uri, false, $context);

        if($body === false){
            trigger_error(
                "Unable to contact the Server. Are outbound connections disabled? " .
                "(If a proxy is required for outbound traffic, you may configure " .
                "the honey pot to use a proxy. For instructions, visit " .
                "http://www.projecthoneypot.org/settings_help.php)",
                E_USER_ERROR
            );
        }

        return new TextResponse($body);
    }
}

trait AliasingTrait
{
    private $aliases = [];

    public function searchAliases($search, array $aliases, array $collector = [], $parent = null): array
    {
        foreach($aliases as $alias => $value){
            if(is_array($value)){
                return $this->searchAliases($search, $value, $collector, $alias);
            }
            if($search === $value){
                $collector[] = $parent ?? $alias;
            }
        }

        return $collector;
    }

    public function getAliases($search): array
    {
        $aliases = $this->searchAliases($search, $this->aliases);
    
        return !empty($aliases) ? $aliases : [$search];
    }

    public function aliasMatch($alias, $key)
    {
        return $key === $alias;
    }

    public function setAlias($key, $alias)
    {
        $this->aliases[$alias] = $key;
    }

    public function setAliases(array $array)
    {
        array_walk($array, function($v, $k){
            $this->aliases[$k] = $v;
        });
    }
}

abstract class Data
{
    protected $key;
    protected $value;

    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function key()
    {
        return $this->key;
    }

    public function value()
    {
        return $this->value;
    }
}

class DataCollection
{
    use AliasingTrait;

    private $data;

    public function __construct(Data ...$data)
    {
        $this->data = $data;
    }

    public function set(Data ...$data)
    {
        array_map(function(Data $data){
            $index = $this->getIndexByKey($data->key());
            if(is_null($index)){
                $this->data[] = $data;
            } else {
                $this->data[$index] = $data;
            }
        }, $data);
    }

    public function getByKey($key)
    {
        $key = $this->getIndexByKey($key);
        return !is_null($key) ? $this->data[$key] : null;
    }

    public function getValueByKey($key)
    {
        $data = $this->getByKey($key);
        return !is_null($data) ? $data->value() : null;
    }

    private function getIndexByKey($key)
    {
        $result = [];
        array_walk($this->data, function(Data $data, $index) use ($key, &$result){
            if($data->key() == $key){
                $result[] = $index;
            }
        });

        return !empty($result) ? reset($result) : null;
    }
}

interface Transcriber
{
    public function transcribe(array $data): DataCollection;
    public function canTranscribe($value): bool;
}

class StringData extends Data
{
    public function __construct($key, string $value)
    {
        parent::__construct($key, $value);
    }
}

class CompressedData extends Data
{
    public function __construct($key, string $value)
    {
        parent::__construct($key, $value);
    }

    public function value()
    {
        $url_decoded = base64_decode(str_replace(['-','_'],['+','/'],$this->value));
        if(substr(bin2hex($url_decoded), 0, 6) === '1f8b08'){
            return gzdecode($url_decoded);
        } else {
            return $this->value;
        }
    }
}

class FlagData extends Data
{
    private $data;

    public function setData($data)
    {
        $this->data = $data;
    }

    public function value()
    {
        return $this->value ? ($this->data ?? null) : null;
    }
}

class CallbackData extends Data
{
    private $arguments = [];

    public function __construct($key, callable $value)
    {
        parent::__construct($key, $value);
    }

    public function setArgument($pos, $param)
    {
        $this->arguments[$pos] = $param;
    }

    public function value()
    {
        ksort($this->arguments);
        return \call_user_func_array($this->value, $this->arguments);
    }
}

class DataFactory
{
    private $data;
    private $callbacks;

    private function setData(array $data, string $class, DataCollection $dc = null)
    {
        $dc = $dc ?? new DataCollection;
        array_walk($data, function($value, $key) use($dc, $class){
            $dc->set(new $class($key, $value));
        });
        return $dc;
    }

    public function setStaticData(array $data)
    {
        $this->data = $this->setData($data, StringData::class, $this->data);
    }

    public function setCompressedData(array $data)
    {
        $this->data = $this->setData($data, CompressedData::class, $this->data);
    }

    public function setCallbackData(array $data)
    {
        $this->callbacks = $this->setData($data, CallbackData::class, $this->callbacks);
    }

    public function fromSourceKey($sourceKey, $key, $value)
    {
        $keys = $this->data->getAliases($key);
        $key = reset($keys);
        $data = $this->data->getValueByKey($key);

        switch($sourceKey){
            case 'directives':
                $flag = new FlagData($key, $value);
                if(!is_null($data)){
                    $flag->setData($data);
                }
                return $flag;
            case 'email':
            case 'emailmethod':
                $callback = $this->callbacks->getByKey($key);
                if(!is_null($callback)){
                    $pos = array_search($sourceKey, ['email', 'emailmethod']);
                    $callback->setArgument($pos, $value);
                    $this->callbacks->set($callback);
                    return $callback;
                }
            default:
                return new StringData($key, $value);
        }
    }
}

class DataTranscriber implements Transcriber
{
    private $template;
    private $data;
    private $factory;

    private $transcribingMode = false;

    public function __construct(DataCollection $data, DataFactory $factory)
    {
        $this->data = $data;
        $this->factory = $factory;
    }

    public function canTranscribe($value): bool
    {
        if($value == '<BEGIN>'){
            $this->transcribingMode = true;
            return false;
        }

        if($value == '<END>'){
            $this->transcribingMode = false;
        }

        return $this->transcribingMode;
    }

    public function transcribe(array $body): DataCollection
    {
        $data = $this->collectData($this->data, $body);

        return $data;
    }

    public function collectData(DataCollection $collector, array $array, $parents = []): DataCollection
    {
        foreach($array as $key => $value){
            if($this->canTranscribe($value)){
                $value = $this->parse($key, $value, $parents);
                $parents[] = $key;
                if(is_array($value)){
                    $this->collectData($collector, $value, $parents);
                } else {
                    $data = $this->factory->fromSourceKey($parents[1], $key, $value);
                    if(!is_null($data->value())){
                        $collector->set($data);
                    }
                }
                array_pop($parents);
            }
        }
        return $collector;
    }

    public function parse($key, $value, $parents = [])
    {
        if(is_string($value)){
            if(key($parents) !== NULL){
                $keys = $this->data->getAliases($key);
                if(count($keys) > 1 || $keys[0] !== $key){
                    return \array_fill_keys($keys, $value);
                }
            }

            end($parents);
            if(key($parents) === NULL && false !== strpos($value, '=')){
                list($key, $value) = explode('=', $value, 2);
                return [$key => urldecode($value)];
            }

            if($key === 'directives'){
                return explode(',', $value);
            }

        }

        return $value;
    }
}

interface Template
{
    public function render(DataCollection $data): string;
}

class ArrayTemplate implements Template
{
    public $template;

    public function __construct(array $template = [])
    {
        $this->template = $template;
    }

    public function render(DataCollection $data): string
    {
        $output = array_reduce($this->template, function($output, $key) use($data){
            $output[] = $data->getValueByKey($key) ?? null;
            return $output;
        }, []);
        ksort($output);
        return implode("\n", array_filter($output));
    }
}

class Script
{
    private $client;
    private $transcriber;
    private $template;
    private $templateData;
    private $factory;

    public function __construct(HttpClient $client, Transcriber $transcriber, Template $template, DataCollection $templateData, DataFactory $factory)
    {
        $this->client = $client;
        $this->transcriber = $transcriber;
        $this->template = $template;
        $this->templateData = $templateData;
        $this->factory = $factory;
    }

    public static function run(string $host, int $port, string $script, string $settings = '')
    {
        $client = new ScriptClient($settings);

        $templateData = new DataCollection;
        $templateData->setAliases([
            'doctype'   => 0,
            'head1'     => 1,
            'robots'    => 8,
            'nocollect' => 9,
            'head2'     => 1,
            'top'       => 2,
            'legal'     => 3,
            'style'     => 5,
            'vanity'    => 6,
            'bottom'    => 7,
            'emailCallback' => ['email','emailmethod'],
        ]);

        $factory = new DataFactory;
        $factory->setStaticData([
            'doctype' => '<!DOCTYPE html>',
            'head1'   => '<html><head>',
            'head2'   => '<title>Momentous Reedy</title></head>',
            'top'     => '<body id="top"><div align="center">',
            'bottom'  => '</div></body></html>',
        ]);
        $factory->setCompressedData([
            'robots'    => 'H4sIAAAAAAAAA7PJTS1JVMhLzE21VSrKT8ovKVZSSM7PK0nNK7FVSsvPyckv18nLz8xLSa1QsrPBpzgvP7EoOSOzLFXJDgC9o9kCVQAAAA',
            'nocollect' => 'H4sIAAAAAAAAA7PJTS1JVMhLzE21VcrL103NTczM0U3Oz8lJTS7JzM9TUkjOzytJzSuxVdJXsgMAKsBXli0AAAA',
            'legal'     => 'H4sIAAAAAAAAA61aa3PbOLL9fn8FrrOVTaocx55NHGeZSZXj0STampFzLSVT8xEiIRETkuAAoDzaX7_9AEhQj7zuVkUxKYF4NE6fPt3gKy-XlRK5qqpWFoVu1j-enJ_QvWtlHu-XxhbK4uXr_3nlLf5XCOe3lfrxZGUa_2Qla11t_ylq0xh8UGUnrx8-uLg8z0T482r5-hU2FbmpjP3xvtRevdavnuJ3r189hV-X9vXDZunaDK8Wpdpv3obm99jCG7E1eGF8qSxe_KYePnh5lTmNN1bJYr8HHweEFnKtGk89lTDH84usfvW_T56IvFLS5p2HtVhZab8Vqm611bmshMqdl17n4smT12f4aGOahw-ePc88j7_ksWkp6ep7G6RLnLc8bE7PzAzcXZxnT97hnWt1wb_iXWvxx_N_ZLRM2XlTx2EmtdQVXkzx8ctMaEdtKtMovCilE5J65N9hTXi3kVU39O-8sXj3PGO7WP5Jff1aJjIvBT8sdcPPPDvPTv7VFWxhJWjursux72f8vOQlF6KlfVyJleJHX77I-KftccxQK7Ijz9ZuhiXgePLrZ_-76USOF7Xc8nNyiea5eJ4NRvqonfZDn1eX2XQ-XeDN--u7xe80kw9v5hNq8tQX3-EkyeTwg3CiETvhuuUwZa8s7b9XwQM821TVnwe8E2DioRtwMbqzsnEr7u8sDn3QWz8l3hqNd6BZGZsd_yXpgMcD37K094TKl5lseGq8Caf7XeWhq2XEETzkBIENHbQHx7BcoDc73FmVk-nOBGz-8PWGurrK6Ks1b_6B0VdhdDk8CaalvWrWwpeEJdUEl_wSEMnFpfVb9kURthPvdK4lubdaa0f9V-Q0sNjj9BjGgqlj02fZyvBf_B5onDvMvQ6MQ3fcHqwRHPAqMxv-7qumbxqniD0BsUroujXOaYws3khYkSuN9UIWG9nkqkD2JI5oW6Ba3gXke-zHusFxb-_m4nqw8C043Q-ZmOM3__dh8vDBiyuYxhWv67_kcWJlCRZ_qDzZPCfWZsOOKPOco4RVjYSlwgqchH2AJZEdpVWr1CX3d2kddkn3VJcH6xNzw_4sq8ENP2925yEujcjJ8LTdKbVR7D96Teauqi2w8mXvF-FXp2jgdaP_rRJ3yT81bJLGMLCLp4Y2q1SOiUM78Rs9IZtCfDFYDF3Totfs70oYdhf-6RpgyVbAyZ2HR8GojIpahYHCHGHc4W5p2ZORzE1PCxA01VfNDfzOJL05UycjTQtGd9cq2wBZtRougG9MVUkrClXp5s8O5AQgTTagmhAPiuzXB9N-oLd3kwndTGYPH1xeZdPZMM6bO8D15fP_IqRrAbCj9ZlATLACjpS8v61n4uoAHqxpCp2EUieYPPSat4vZZ4iEIVrM309uplHG0XAaW15kjQt-fcaYCSFf_UXdFUR2FxdZ2l0g1OY479LoB-SdjL-XfQAw98zoqhi6rpRzw12h2Q-oU4wSzGRWIvcK6VD8wY7mwHFBLSPb2Q3EGdOIFjzwU7WNBIAhe9AMf7U2ce2jgVUfjZgyjZgHGcv1uyr0wOemOXlMba6LmvUYis2LH7JKB4mFLb22qQjsddclS791SYZYgQPdozBaoQhuhEfBUGsgeFg-LNuzb4JNWE5zMI7R72y8qwfXb75OWORH5UMpNwmk7yEfUD0JP89W_Ie-AjITPXYX78jZFuz2D15AsLueUQS8eSduf_4uJ_zyzMcbGPUwKzkIQOwPFALWIsSHQLtJQA50GVQyfVV0snpELf0BcRDDzpr2tO1Wq61YdvknBdG5VbmXkPeIStZtRHJpIAfa8fLrX0Zb9MvI46PbMhxmITN6x-B0PCxARgFSJSha05YgaQBRHljAVfBf3Toc_DRZIIRblc6h5K10wgXKZtmwVmypJg0yiL5SNhHuQGHLLgKUwx5h8-9OKIYseUEF1z9cEJmCu3xVUMORwQmSwYEh6M6V0aeAgSqOmZ4nQ0bq6Y86Axa55_zxqIyH7l5mpqM4jJlWQmPA7yNT7aXfd-lmrQFw3dhawNXsGsAkjiZkROf6Pt9dk_76OBme-nUynw935E0vs_n3ug1puclolqy30DQEn1pVpoDsG3pRopaflCv1you1BQytugrBw3T_PBuoELiel4VpkwvQDFn2YwiOtGKQcAMTYvY4MiVM7obWNoP0bg_xG81Zf1czBjlzMUu2aYq4U06Ckx2CmXo1JOjo3apmZEAafhgLLmDhw1z8rQ-puHlmmNO08azMemsAAHkcy81LvUwd-_37sTNvQevoOMmLLKhhnQyxDPNdD9H-KqgcmLoaCBgcrxHRCiBNA8i9KDUttQL2sTvmntzc3pE4SudkxbQhHKw1Sm7YWAXphFh1HkgnlzVIZ1duRQmizNwjGHjAuAqYh-NKQ9foPxPw-1L38Xm-mM7eUuCkeDB5xADHzRcLAOc3YRsW8uv8eDSg4DNLV4hLYt3b0_9LFELoi8fz8UCCY03c1SGHNqhXCFJku6UqnC5AxJRSW2wD-qADDKK5Io9g67-YAK1y6db01I869eof2WIk-Yz9OyMd7z4GWcVSEbBPi6GNVrI4HYUWq4JkJw_S1XGJRBBWKoie5-dnx837e9x4SBqDiRqQbIwgK_-tjTN9Koqyj1Ya5VsxSpWQAa7f_DLyfn8K7BRxDRR2HzKwDQhsvLiBSXDWwj0GQcSRK5QcRa_2AYS8X8fLJtCAbBjHhC6BDHc8Zz6K0ADil-D9L86zfdEE01SseSF8knBQzBNbUSd4CmUT_JyKt9dU8Xp8XKkubmlGv87FLY_7rR7z084CLl9kt7N91o1OW-ig8sxh40XgYHEWW-TBcoVmkDo9sITnOusV7xELAcgmsQEnEwd0Vez_PqmgJttxN6UVLA4opUrnathKVgVO0LTkfcUl7dzU7X6_DXrwCLUS6FAOnXVAikPcgQBT9M4VQgG7G2ggodOeB2l3_ds-YijhwYtSGMsFhgNJWEzSllTPyr145JOoodjcVMLH3EQw-FWo9PS2OM8alfMc7UbvWPUjlV73LPreGh-7O4nhDwbLy2hoTMU-I46ZLWKn7yZ3nJsv3k2OA346jE-UOLn7-O2YB1cBMp3e7q9JhkJJAwn0IiUo4icRtyVm2Sx1DpQsY7CQrIEbDjOKt9F7znkbox2FgmaXVm5nBwJZLDj-nE4b8sBQCc0rrv67A9OJj5aEWpBzZlcagTfEhVIhaBDkWLA6SpKSFgKy0XTQwurlEjDbNaVsW0x4bKzORC7A3EvxyU8LzgSOBUEC8n7XtS0kyw4DpQXpCFmSsd7FRy1g7OFQVbWholBVQS9x0BNYxgqMnRjzejYmuX-l9gPPavmcx8YImrBeKCuyFwLK-_ihVDMEk7kPyB9EbcE5TnS46Mmgn8xqd6_vRrP7MB-hkjw0sGhFfUyLkPkXsYQWCs4hJ8LPW7aw3OiqwrFBeXQrmfvOgmlXoNfgD25ng7Y3rQ-1hVMxJ9D_xPJ7Qc42vZmcfbOLAX5pBqba5tK2mEWkU5Cd83jcJ2nY0fpvR_BWdVhsXwZ9sxWbZIse-TKh9c_UsLhZacIhR0-EtOAWkpVd1qNgfne8fDIb0Yfpa9Wrzm4AVU5XIE0igBUJsqtsCG52o5JCGC9mzcltmc7EmtbG1OACsQhwJWUJWVRexv6lrkRyPDKq-UL6TuFJM-kovS6XxkL6i5UI9LlKr5TXtQLHpbKb9h0iQnKKx-GBFaTBsvWOnT7cEZ3OfxqZI1dB2MWpn2drsbIxMFxl4WCF06iijgWl46dYaQQ7E7-H0Bv8j_Mv0ITlEVUXcdCyGU2-s4rJCITXs710iExK5Uo05UqzYoC0Pjgf3YlQ4ZMBCYB2qroAMwJ8_9DA_BZ4UVr4Z7qe3vBzexdSoZ-nEz566Wf39dn9QCKTfT0RHObEl4EdLEQ72fYY6jN2F0VeUmM5oXpBOHWC0WxhPADEK_DjUhXgzKKSACsNqTAIpo3CUyj4cqVdGZNDZm_uLwpE3kZ7PGSxxsCp8eEQ1kF39m725B3M7nn264g5QhUgVCO4YhU8Ih50sqiyrPP3K-PKS7sdJsk5d_qmAMAhKThjmkQ-uHSeqRhi2YZHLERf7t0TrdObHSmio79QzcAcOGfOe1bDFqsvJqu9h8w5UIW7vnxG8z-Q3MleqV1hIYwWZxXnYepz0m4sL3q3ejOObs6Fc-gT9hcDKTLFolIuNUwZmclDEKG6KYDosdAUPbHewi84mOZscNBn4IaLO1K2N6CgFt_uQEnYxXQRQHqYTXp5Z5Mi8Vo84uJ_fcAwRYTzKvZPtSjHsc3e9xR_ka0EKdtkh-Jp9yg6XWKh6cXVKNUEqZ7aF8Lbb0k3WI1P9cJQxEWn9ftbprkA1MAuQLRAqYbhGPaslropOLoxkgrcPNqRmPz31WLQd0lFFtiwWQ8jSLGXbcz2mQu4cs3HLmKhktcK8PUggs9jGH74GoUltYY1kTy1Hlg4qbnsY78MdMHhSK2lLT4XIPjbHN_TGvq65-M69m9I9sX7tIvZ_GcuaE2JSYmwFpPvhyge_VPWBSFRJqdtsuFM-jgEh7aMM7thrqpYXzos9pLVmfAYq5w8A6DM_Y5hIDcMUilBHh7M0ROVrrlQsx0fPXPuZ4dXq6gxFieHNmVXJ5qmjxhh4-qWuy24rnkq1gwGUyVqfJdsQ2YtluHcITojvs4CrknvGvTnjRvVy7f4Lo-NqObyXtM1jg-fPaeE8Wd0E5rNKn4TSP0hHZIPb12kn-JMXDeRB15kKM8Y_byesWYhjTAlBnhLU5uL69l3aIb4xON4DFFoHwrP4d0gnjgvIlcnj2l_VaiW4s2nEATF6IijD7cNEIXZBc0xeQKJI7-BU4iwarrDdIW1h8KMF3LHXCcCKhap1CiR7kJ2akEqrymTAFUOV7CJCpBm6rpr8EQsZD8Vl6sboCTX79glKG6yu65YnPdlepBHoG3iHWgjm54IhyrlbrYXfLfLy-Oxc1jQPPhmVBm6SA189SIbuYQKpxDHe_6DgcX5xIES93DuRad5KtDBgi7wFabm0GrC6BAT2Lda078bQ8cgIyYl8rsV13xcfnPDuYwxnxyeM1GyCuJpTSf8k_ehTFKoVCl_O7Zbazac_WC5eKPVPcSwFZJN2Hre95BO28eh3n1AEBWDIDrPqkEJco2Q6EMuY48uzfZ7ItgzHzORDz6HIuaedQc7YNvxto7ed2VeaFWfUIGaMDZR1I3n7TDNKTlQfHFhfbp3dhuPjdyh2fVvyoXyKZ4BHc-uqGE8YrPgwzQHx2S5Vg2-Dtd2tjVgmsJ2zSc0lU45f6MaYhHLRaKVruqtwAOuRkDSAYtqPCVQXLpoZaVQUYRCgmOeQSIirmjxTKBRSBTdmt8aaYpDq4TkmeteRSBvzQfG0fFGpsGTV0h4736iGz5Wuvl_RPIiOYTivYlJRwCX5GNhqw7O_dCHYOFHcPPbXik3_XL6Qu159jRmYl_ouhewUegwcOOx6DBzwWJ4xfx9cJbxxSAiCva43WZHUx-s6o1N_pRelX9Kbw3BBfzwHwuc3XhwLwAA',
            'style'     => 'H4sIAAAAAAAAAyXMywmAMAwA0FUEr36vbfHYPaKmUIiJNAEr4u4efAO8oHYTLjDohcezCUlxbYzRJ2Fzq9DezNNZGygZqFNg7RVLTt6wWr_jJgUsCzsWRv-G8f8-NAFuqVcAAAA',
            'vanity'    => 'H4sIAAAAAAAAA22S207DMAyGX8XKbmEdp0nL2goxDSEk2MThgsu0ydpAFkeOWdnbk5ZxwUGRJTuKv_93kpxV5QzUxrkYVG19U4iJ6MugtD6UFZI21GeR984UolL1W0P47rUczWazeWc1t_L0bBI-5qLMmVJo2ClnG18IxvDdeIBKOAkfcJriIsV56vqSOCbbtCwjOquHI6PFYtETkzcPB8YGPcsKnYZeDxRZ5Y6i8vE4GrKbeY0OSY6m0-k8KcveU8Bo2aKXZJxiuzOJeZlnPbXMM9Z_7MIhd2bDAn6ZP0uqk7TOv6ZV0JLZFKJlDjLLuq4bB8JXU3OL3uwD8hipyQTUTsVYiNiZrSjvlndXywdYXcP6YXW7XDzBzep--QLr1VOeqTKv6F_yu0-mt-Mat-IH7jHtwo2inYlsCNaEnAykkeHecIf01gOTrZ3VRkO1h-cBNEgNF5D1j5YNv6H8BGqpASAVAgAA',
        ]);
        $factory->setCallbackData([
            'emailCallback' => function($email, $style = null){
                $value = $email;
                $display = 'style="display:' . ['none',' none'][random_int(0,1)] . '"';
                $style = $style ?? random_int(0,5);
                $props[] = "href=\"mailto:$email\"";
        
                $wrap = function($value, $style) use($display){
                    switch($style){
                        case 2: return "<!-- $value -->";
                        case 4: return "<span $display>$value</span>";
                        case 5:
                            $id = 'y9br15efr';
                            return "<div id=\"$id\">$value</div>\n<script>document.getElementById('$id').innerHTML = '';</script>";
                        default: return $value;
                    }
                };
        
                switch($style){
                    case 0: $value = ''; break;
                    case 3: $value = $wrap($email, 2); break;
                    case 1: $props[] = $display; break;
                }
        
                $props = implode(' ', $props);
                $link = "<a $props>$value</a>";
        
                return $wrap($link, $style);
            }
        ]);

        $transcriber = new DataTranscriber($templateData, $factory);

        $template = new ArrayTemplate([
            'doctype',
            'injDocType',
            'head1',
            'injHead1HTMLMsg',
            'robots',
            'injRobotHTMLMsg',
            'nocollect',
            'injNoCollectHTMLMsg',
            'head2',
            'injHead2HTMLMsg',
            'top',
            'injTopHTMLMsg',
            'actMsg',
            'errMsg',
            'customMsg',
            'legal',
            'injLegalHTMLMsg',
            'altLegalMsg',
            'emailCallback',
            'injEmailHTMLMsg',
            'style',
            'injStyleHTMLMsg',
            'vanity',
            'injVanityHTMLMsg',
            'altVanityMsg',
            'bottom',
            'injBottomHTMLMsg',
        ]);

        $hp = new Script($client, $transcriber, $template, $templateData, $factory);
        $hp->handle($host, $port, $script);
    }

    public function handle($host, $port, $script)
    {
        $data = [
            'tag1' => 'a424633113682ca8e4d45ba885094829',
            'tag2' => 'c3dba92154d59464b3abd4423d903b81',
            'tag3' => '3649d4e9bcfd3422fb4f9d22ae0a2a91',
            'tag4' => md5_file(__FILE__),
            'version' => "php-".phpversion(),
            'ip'      => $_SERVER['REMOTE_ADDR'],
            'svrn'    => $_SERVER['SERVER_NAME'],
            'svp'     => $_SERVER['SERVER_PORT'],
            'sn'      => $_SERVER['SCRIPT_NAME']     ?? '',
            'svip'    => $_SERVER['SERVER_ADDR']     ?? '',
            'rquri'   => $_SERVER['REQUEST_URI']     ?? '',
            'phpself' => $_SERVER['PHP_SELF']        ?? '',
            'ref'     => $_SERVER['HTTP_REFERER']    ?? '',
            'uagnt'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        $headers = [
            "User-Agent: PHPot {$data['tag2']}",
            "Content-Type: application/x-www-form-urlencoded",
            "Cache-Control: no-store, no-cache",
            "Accept: */*",
            "Pragma: no-cache",
        ];

        $subResponse = $this->client->request("POST", "http://$host:$port/$script", $headers, $data);
        $data = $this->transcriber->transcribe($subResponse->getLines());
        $response = new TextResponse($this->template->render($data));

        $this->serve($response);
    }

    public function serve(Response $response)
    {
        header("Cache-Control: no-store, no-cache");
        header("Pragma: no-cache");

        print $response->getBody();
    }
}

Script::run(__REQUEST_HOST, __REQUEST_PORT, __REQUEST_SCRIPT, __DIR__ . '/phpot_settings.php');

