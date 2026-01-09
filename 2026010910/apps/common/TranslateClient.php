<?php

namespace app\common;

use core\basic\Config;

class TranslateClient
{
    public function checkReady()
    {
        if (! function_exists('curl_init')) {
            return $this->fail('PHP未启用curl扩展，无法调用翻译接口');
        }

        $baseUrl = $this->getBaseUrl();
        $accessKeyId = $this->getAccessKeyId();
        $accessKeySecret = $this->getAccessKeySecret();

        if ($baseUrl === '') {
            return $this->fail('翻译接口地址未配置');
        }
        if ($accessKeyId === '') {
            return $this->fail('翻译接口AccessKeyId未配置');
        }
        if ($accessKeySecret === '') {
            return $this->fail('翻译接口AccessKeySecret未配置');
        }

        return array('success' => true);
    }

    public function translateContent($content, $targetLang, $sourceLang = 'auto', $returnType = 'html')
    {
        $content = (string) $content;
        $targetLang = trim((string) $targetLang);
        $sourceLang = trim((string) $sourceLang) ?: 'auto';
        $returnType = trim((string) $returnType) ?: 'html';

        if ($content === '') {
            return $this->fail('翻译内容不能为空');
        }
        if ($targetLang === '') {
            return $this->fail('目标语言不能为空');
        }

        $payload = array(
            'content' => $content,
            'target_lang' => $targetLang,
            'source_lang' => $sourceLang,
            'return_type' => $returnType
        );

        $result = $this->postJson('/api/server/translate/content', $payload);
        if (! $result['success']) {
            return $result;
        }

        $translated = $this->extractTranslatedContent($result['json'], $result['raw']);
        if ($translated === null || $translated === '') {
            return $this->fail('翻译接口返回内容为空', array(
                'http_code' => $result['http_code'],
                'raw' => $result['raw']
            ));
        }

        return array(
            'success' => true,
            'data' => $translated,
            'http_code' => $result['http_code'],
            'json' => $result['json'],
            'raw' => $result['raw']
        );
    }

    private function postJson($path, array $payload)
    {
        if (! function_exists('curl_init')) {
            return $this->fail('PHP未启用curl扩展，无法调用翻译接口');
        }

        $baseUrl = $this->getBaseUrl();
        $accessKeyId = $this->getAccessKeyId();
        $accessKeySecret = $this->getAccessKeySecret();

        if ($baseUrl === '') {
            return $this->fail('翻译接口地址未配置');
        }
        if ($accessKeyId === '') {
            return $this->fail('翻译接口AccessKeyId未配置');
        }
        if ($accessKeySecret === '') {
            return $this->fail('翻译接口AccessKeySecret未配置');
        }

        $timestamp = (string) time();
        $headers = $this->buildAuthHeaders($accessKeyId, $accessKeySecret, $timestamp);

        $url = $this->joinUrl($baseUrl, $path);
        $requestBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($requestBody === false) {
            return $this->fail('请求数据序列化失败');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return $this->fail('翻译接口请求失败：' . $error);
        }

        if ($httpCode !== 200) {
            return $this->fail('翻译接口返回错误：HTTP ' . $httpCode, array(
                'http_code' => $httpCode,
                'raw' => $response,
                'url' => $url
            ));
        }

        $json = json_decode((string) $response, true);

        return array(
            'success' => true,
            'http_code' => $httpCode,
            'json' => is_array($json) ? $json : array(),
            'raw' => (string) $response
        );
    }

    private function buildAuthHeaders($accessKeyId, $accessKeySecret, $timestamp)
    {
        $accessKeyId = (string) $accessKeyId;
        $accessKeySecret = (string) $accessKeySecret;
        $timestamp = (string) $timestamp;

        $sign = $this->calculateSign($accessKeyId, $accessKeySecret, $timestamp);

        return array(
            'Content-Type: application/json',
            'X-Access-Key-Id: ' . $accessKeyId,
            'X-Timestamp: ' . $timestamp,
            'X-Sign: ' . $sign
        );
    }

    private function calculateSign($accessKeyId, $accessKeySecret, $timestamp)
    {
        $payload = (string) $accessKeyId . (string) $timestamp . (string) $accessKeySecret;
        return hash('sha256', $payload);
    }

    private function extractTranslatedContent($json, $raw)
    {
        if (is_array($json)) {
            if (isset($json['data']['content']) && is_string($json['data']['content'])) {
                return $json['data']['content'];
            }
            if (isset($json['data']['html']) && is_string($json['data']['html'])) {
                return $json['data']['html'];
            }
            if (isset($json['content']) && is_string($json['content'])) {
                return $json['content'];
            }
            if (isset($json['html']) && is_string($json['html'])) {
                return $json['html'];
            }
            if (isset($json['data']) && is_string($json['data'])) {
                return $json['data'];
            }
        }

        $raw = (string) $raw;
        if ($raw !== '') {
            return $raw;
        }

        return null;
    }

    private function getBaseUrl()
    {
        $url = Config::get('TranslateUrl') ?: 'https://t.dourry.cn/';
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        return rtrim($url, '/') . '/';
    }

    private function getAccessKeyId()
    {
        return trim((string) (Config::get('TranslateAccessKeyId') ?: ''));
    }

    private function getAccessKeySecret()
    {
        return trim((string) (Config::get('TranslateAccessKeySecret') ?: ''));
    }

    private function joinUrl($baseUrl, $path)
    {
        $baseUrl = rtrim((string) $baseUrl, '/') . '/';
        $path = ltrim((string) $path, '/');
        return $baseUrl . $path;
    }

    private function fail($message, array $context = array())
    {
        $result = array(
            'success' => false,
            'error' => (string) $message
        );
        if ($context) {
            $result['context'] = $context;
        }
        return $result;
    }
}
