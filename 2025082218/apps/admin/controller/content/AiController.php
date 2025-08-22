<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author AI Assistant
 * @email support@doyecms.com
 * @date 2024年1月15日
 * AI文字助手控制器
 */

namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\ContentModel;
use core\basic\Config;

class AiController extends Controller
{
    private $model;
    
    public function __construct()
    {
        $this->model = new ContentModel();
    }
    
    /**
     * 初始化AI配置项
     * @return void
     */
    public function initConfig()
    {
        // AI配置项数据
        $aiConfigs = [
            [
                'name' => 'ai_enabled',
                'value' => '0',
                'type' => '1',
                'description' => '启用AI助手功能',
                'sorting' => 1000
            ],
            [
                'name' => 'ai_api_url',
                'value' => 'https://api.openai.com/v1/chat/completions',
                'type' => '2',
                'description' => 'AI接口地址',
                'sorting' => 1001
            ],
            [
                'name' => 'ai_api_key',
                'value' => '',
                'type' => '2',
                'description' => 'AI API密钥',
                'sorting' => 1002
            ],
            [
                'name' => 'ai_model',
                'value' => 'gpt-3.5-turbo',
                'type' => '2',
                'description' => 'AI模型名称',
                'sorting' => 1003
            ],
            [
                'name' => 'ai_timeout',
                'value' => '600',
                'type' => '1',
                'description' => '请求超时时间(秒)',
                'sorting' => 1004
            ],
            [
                'name' => 'ai_max_tokens',
                'value' => '2000',
                'type' => '1',
                'description' => '最大Token数',
                'sorting' => 1005
            ],
            [
                'name' => 'ai_temperature',
                'value' => '0.7',
                'type' => '2',
                'description' => '温度参数(0-1)',
                'sorting' => 1006
            ]
        ];
        
        try {
            $success = 0;
            $skip = 0;
            
            foreach ($aiConfigs as $config) {
                // 检查配置项是否已存在
                $existing = $this->model->table('ay_config')->where('name', $config['name'])->find();
                
                if ($existing) {
                    $skip++;
                    continue;
                }
                
                // 插入新配置项
                $result = $this->model->table('ay_config')->insert($config);
                
                if ($result) {
                    $success++;
                }
            }
            
            json(1, "AI配置项初始化完成！成功插入 {$success} 项，跳过 {$skip} 项已存在的配置。");
            
        } catch (Exception $e) {
            json(0, '初始化失败：' . $e->getMessage());
        }
    }
    
    /**
     * 公开的初始化配置方法（不需要登录验证）
     */
    public function publicInitConfig()
    {
        // 临时跳过权限检查
        $this->initConfig();
    }
    
    /**
     * AI翻译功能
     * @return void
     */
    public function translate()
    {
        if (session('formcheck') != post('formcheck')) {
            json(0, '表单验证失败');
        }
        
        if ($_POST) {
            $text = post('text');
        $target_lang = post('target_lang', 'var');
            
            if (empty($text)) {
                json(0, '请输入要翻译的文本');
            }
            
            if (empty($target_lang)) {
                json(0, '请选择目标语言');
            }
            
            // 语言映射
            $langMap = [
                'en' => '英语',
                'zh' => '中文',
                'ar' => '阿拉伯语',
                'pt' => '葡萄牙语',
                'fr' => '法语',
                'es' => '西班牙语',
                'ru' => '俄语'
            ];
            
            $langName = $langMap[$target_lang] ?? $target_lang;
            
            try {
                $result = $this->callAiApi('translate', $text, $langName);
                json(1, $result['content'], '翻译成功', ['model' => $result['model']]);
            } catch (Exception $e) {
                json(0, 'AI翻译失败：' . $e->getMessage());
            }
        } else {
            json(0, '请求方式错误');
        }
    }
    
    /**
     * AI润色功能
     * @return void
     */
    public function polish()
    {
        if (session('formcheck') != post('formcheck')) {
            json(0, '表单验证失败');
        }
        
        if ($_POST) {
            $text = post('text');
        $style = post('style');
            
            if (empty($text)) {
                json(0, '请输入要润色的文本');
            }
            
            // 风格映射
            $styleMap = [
                'professional' => '专业正式',
                'casual' => '轻松随意', 
                'academic' => '学术严谨',
                'creative' => '创意生动',
                'concise' => '简洁明了'
            ];
            
            $styleName = $styleMap[$style] ?? '专业正式';
            
            try {
                $result = $this->callAiApi('polish', $text, $styleName);
                json(1, $result['content'], '润色成功', ['model' => $result['model']]);
            } catch (Exception $e) {
                json(0, 'AI润色失败：' . $e->getMessage());
            }
        } else {
            json(0, '请求方式错误');
        }
    }
    
    /**
     * AI优化功能（SEO相关）
     * @return void
     */
    public function optimize()
    {
        if ($_POST) {
            $type = post('type', 'var'); // title, subtitle, url, keywords, description, tags
        $content = post('content');
        $original_text = post('original_text');
            
            if (empty($type)) {
                json(0, '请指定优化类型');
            }
            
            if (empty($content) && empty($original_text)) {
                json(0, '请提供内容或原始文本');
            }
            
            try {
                $result = $this->callAiApi('optimize', $original_text ?: $content, $type);
                json(1, $result['content'], $result['model']);
            } catch (Exception $e) {
                json(0, 'AI优化失败：' . $e->getMessage());
            }
        } else {
            json(0, '请求方式错误');
        }
    }
    
    /**
     * AI代写功能
     * @return void
     */
    public function generate()
    {
        if (session('formcheck') != post('formcheck')) {
            json(0, '表单验证失败');
        }
        
        if ($_POST) {
            $topic = post('topic');
            $type = post('type');
            $length = intval(post('length') ?: 500);
            $style = post('style');
            $language = post('language');
            
            if (empty($topic)) {
                json(0, '请输入写作主题');
            }
            
            try {
                $param = $type . '|' . $length . '|' . $style . '|' . $language;
                $result = $this->callAiApi('generate', $topic, $param);
                json(1, $result['content'], '代写成功', ['model' => $result['model']]);
            } catch (Exception $e) {
                json(0, 'AI代写失败：' . $e->getMessage());
            }
        } else {
            json(0, '请求方式错误');
        }
    }
    
    /**
     * 调用AI接口
     * @param string $action 操作类型
     * @param string $text 文本内容
     * @param string $param 额外参数
     * @param int $length 生成长度
     * @return string
     */
    private function callAiApi($action, $text, $param = '', $length = 0)
    {
        // 获取AI配置
        $config = $this->getAiConfig();
        $aiUrl = $config['api_url'] . 'chat/completions';
        $aiKey = $config['api_key'];
        $aiModel = $config['model'];
        
        if (!$config['enabled']) {
            throw new \Exception('AI功能未启用，请在系统设置中启用AI助手功能');
        }
        
        if (empty($aiUrl) || empty($aiKey) || empty($aiModel)) {
            throw new \Exception('AI配置不完整，请先在系统设置中配置AI相关参数');
        }
        
        // 处理多模型随机选择
        $models = explode("\n", $aiModel);
        $selectedModel = trim($models[array_rand($models)]);
        // 构建系统提示词
        $systemPrompt = $this->getSystemPrompt($action, $param);
        
        // 构建用户消息
        $userMessage = $this->buildUserMessage($action, $text, $param, $length);
        
        // 构建请求数据
        $requestData = [
            'model' => $selectedModel,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];
        
        // 发送HTTP请求
        $response = $this->sendHttpRequest($aiUrl, $aiKey, $requestData);
        
        if (!$response) {
            throw new \Exception('AI接口请求失败');
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            throw new \Exception('AI接口返回数据格式错误');
        }
        
        // 返回包含模型信息的结构化数据
        return [
            'content' => trim($result['choices'][0]['message']['content']),
            'model' => $selectedModel
        ];
    }
    
    /**
     * 获取系统提示词
     * @param string $action 操作类型
     * @param string $param 参数
     * @return string
     */
    private function getSystemPrompt($action, $param = '')
    {
        $prompts = [
            'translate' => '你是一位专业的翻译专家，能够准确地将文本翻译成目标语言，保持原文的语义和语调。请直接返回翻译结果，不要添加任何解释。',
            'polish' => '你是一位专业的文字编辑，擅长润色和改进文本，使其更加流畅、准确和优雅。请保持原文的核心意思，只返回润色后的文本。',
            'optimize' => $this->getOptimizePrompt($param),
            'seoOptimize' => '你是一位SEO专家，擅长为网站内容生成各种SEO相关的元素。请严格按照要求的JSON格式返回结果。',
            'generate' => $this->getGeneratePrompt($param),
            'seo' => $this->getSeoPrompt($param)
        ];
        
        return $prompts[$action] ?? '你是一位专业的AI助手，请根据用户需求提供帮助。';
    }
    
    /**
     * 获取优化提示词
     * @param string $type 优化类型
     * @return string
     */
    private function getOptimizePrompt($type)
    {
        $prompts = [
            'title' => '你是一位SEO专家，擅长创建吸引人且符合搜索引擎优化的标题。请基于提供的内容生成一个简洁、有吸引力的标题，强调：语言要和输入内容保持相同，请先确定语言再写！！！直接返回结果，不要添加任何解释，也不要带"title:"。',

            'subtitle' => '你是一位内容编辑专家，擅长创建引人入胜的副标题，副标题需要包含一些关键词。副标题是标题的扩展 可以围绕标题展开更多关键词。强调：语言要和输入内容保持相同，请先确定语言再写！！！直接返回结果，不要添加任何解释，也不要带"subtitle:"。',
            'url' => '你是一位SEO专家，擅长创建SEO友好的URL。请基于提供的标题提取两到三个关键英文单词，最多五个单词，用连字符连接生成一个简洁的英文URL路径（只包含字母、数字和连字符 不能带空格）。例如：“Roller Crusher vs. Impact Crusher for River Pebble Sand Making”->“Roller-Crusher-Impact-Crusher”。只能使用英语。直接返回结果，不要添加任何解释，也不要带"url:"。',
            'keywords' => '你是一位SEO专家，擅长提取和生成关键词。请基于提供的内容提取5-10个相关的关键词，用逗号分隔，例如：“Roller Crusher vs. Impact Crusher for River Pebble Sand Making”->“Roller Crusher,Impact Crusher,River Pebble,Sand Making”。强调：语言要和输入内容保持相同，请先确定语言再写！！！直接返回结果，不要添加任何解释，也不要带"keywords:"。',
            'description' => '你是一位SEO专家，擅长编写meta描述。请基于提供的内容生成一个120-160字符的SEO描述。强调：语言要和输入内容保持相同，请先确定语言再写！！！直接返回结果，不要添加任何解释，也不要带"description:"。',
            'tags' => '你是一位内容分类专家，擅长为内容添加标签。请基于提供的内容生成3-8个相关标签，用英文逗号分隔，要求足够简短，每个标签最多三个单词，例如：“Roller Crusher vs. Impact Crusher for River Pebble Sand Making”->“Roller Crusher,Impact Crusher,River Pebble,Sand Making”。强调：语言要和输入内容保持相同，请先确定语言再写！！！直接返回结果，不要添加任何解释，也不要带"tags:"。'


        ];
        
        return $prompts[$type] ?? '你是一位SEO专家，请根据内容进行相应的优化。';
    }
    
    /**
     * 获取生成内容提示词
     * @param string $param 生成类型、长度和风格
     * @return string
     */
    private function getGeneratePrompt($param)
    {
        $parts = explode('|', $param);
        $type = $parts[0] ?? 'article';
        $length = intval($parts[1] ?? 500);
        $style = $parts[2] ?? 'professional';
        $language = $parts[3] ?? 'zh';
        
        $typeMap = [
            'article' => '文章',
            'title' => '标题',
            'description' => '描述',
            'summary' => '摘要',
            'outline' => '大纲',
            'news' => '新闻稿',
            'blog' => '博客文章',
            'product' => '产品介绍'
        ];
        
        $styleMap = [
            'professional' => '专业正式',
            'casual' => '轻松随意',
            'academic' => '学术严谨',
            'creative' => '创意生动',
            'marketing' => '营销推广'
        ];
        
        $languageMap = [
            'zh' => '中文',
            'en' => '英语',
            'ar' => '阿拉伯语',
            'pt' => '葡萄牙语',
            'fr' => '法语',
            'es' => '西班牙语',
            'ru' => '俄语'
        ];
        
        $typeName = $typeMap[$type] ?? '内容';
        $styleName = $styleMap[$style] ?? '专业';
        $languageName = $languageMap[$language] ?? '中文';
        $lengthText = $length > 0 ? "，字数控制在{$length}字左右" : '';
        
        return "你是一位专业的内容创作者，擅长根据主题生成高质量的{$typeName}。请使用{$languageName}，以{$styleName}的风格创作，确保内容原创、逻辑清晰、语言流畅{$lengthText}。";
    }
    
    /**
     * 获取SEO优化提示词
     * @param string $param 参数（包含关键词和类型）
     * @return string
     */
    private function getSeoPrompt($param)
    {
        $parts = explode('|', $param);
        $keywords = $parts[0] ?? '';
        $type = $parts[1] ?? 'content';
        
        $basePrompt = '你是一位SEO专家，擅长优化内容以提高搜索引擎排名。';
        
        switch ($type) {
            case 'title':
                $prompt = $basePrompt . '请优化标题，使其更符合SEO要求，吸引点击且包含关键词。';
                break;
            case 'description':
                $prompt = $basePrompt . '请优化描述，控制在120-160字符，包含关键词且吸引用户点击。';
                break;
            default:
                $prompt = $basePrompt . '请对内容进行SEO优化，要求：1. 提高搜索引擎友好度 2. 保持内容质量 3. 自然融入关键词。';
        }
        
        if (!empty($keywords)) {
            $prompt .= "重点关键词：{$keywords}。";
        }
        
        return $prompt;
    }
    
    /**
     * 构建用户消息
     * @param string $action 操作类型
     * @param string $text 文本内容
     * @param string $param 参数
     * @param int $length 长度
     * @return string
     */
    private function buildUserMessage($action, $text, $param = '', $length = 0)
    {
        switch ($action) {
            case 'translate':
                return "请将以下文本翻译成{$param}：\n\n{$text}";
            case 'polish':
                return "请润色以下文本：\n\n{$text}";
            case 'optimize':
                return "{$param}\n\n原标题：{$text}";
            case 'seoOptimize':
                return $param; // param已经包含完整的提示词
            case 'generate':
                $parts = explode('|', $param);
                $type = $parts[0] ?? 'article';
                $length = intval($parts[1] ?? 500);
                $style = $parts[2] ?? 'professional';
                
                $typeMap = [
                    'article' => '文章',
                    'title' => '标题', 
                    'description' => '描述',
                    'summary' => '摘要',
                    'outline' => '大纲',
                    'news' => '新闻稿',
                    'blog' => '博客文章',
                    'product' => '产品介绍'
                ];
                
                $typeName = $typeMap[$type] ?? '内容';
                 $lengthText = $length > 0 ? "，字数控制在{$length}字左右" : '';
                 return "请根据以下主题生成{$typeName}{$lengthText}：\n\n{$text}";
             case 'seo':
                $parts = explode('|', $param);
                $keywords = $parts[0] ?? '';
                $type = $parts[1] ?? 'content';
                $keywordText = !empty($keywords) ? "，重点关键词：{$keywords}" : '';
                return "请对以下{$type}进行SEO优化{$keywordText}：\n\n{$text}";
            default:
                return $text;
        }
    }
    
    /**
     * 发送HTTP请求
     * @param string $url API地址
     * @param string $apiKey API密钥
     * @param array $data 请求数据
     * @return string|false
     */
    private function sendHttpRequest($url, $apiKey, $data)
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('HTTP请求错误：' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception('HTTP请求失败，状态码：' . $httpCode . $url . $apiKey . json_encode($data));
        }
        
        return $response;
    }
    
    /**
     * AI SEO优化功能
     */
    public function seoOptimize()
    {
        if (!checkToken()) {
            json(0, '令牌验证失败，请重新登录！');
        }
        
        if ($_POST) {
            $text = post('text');
            $keywords = post('keywords', '');
            $type = post('type'); // content, title, description
            
            if (empty($text)) {
                json(0, '请输入要优化的文本');
            }
            
            try {
                $result = $this->callAiApi('seo', $text, $keywords . '|' . $type);
                json(1, $result['content'], $result['model']);
            } catch (Exception $e) {
                json(0, 'AI SEO优化失败：' . $e->getMessage());
            }
        } else {
            json(0, '请求方式错误');
        }
    }
    
    /**
     * AI标题优化
     */
    public function optimizeTitle()
    {
        if (!$_POST) {
            json(0, '请求方式错误');
        }
        
        $content = post('content');
        
        if (empty($content)) {
            json(0, '请输入要优化的标题');
        }
        
        try {
            // 使用预定义的optimize系统提示词，第三个参数传递'title'作为优化类型
            $result = $this->callAiApi('optimize', $content, 'title');
            json(1, $result['content'], $result['model']);
        } catch (Exception $e) {
            json(0, 'AI标题优化失败：' . $e->getMessage());
        }
    }
    
    /**
     * AI SEO内容优化
     */
    public function seoOptimizeContent()
    {
        if (!$_POST) {
            json(0, '请求方式错误');
        }
        
        $title = post('title');
        $content = post('content');
        $fields = post('fields');
        
        if (empty($title)) {
            json(0, '请输入标题');
        }
        
        if (empty($content)) {
            json(0, '请输入内容');
        }
        
        if (empty($fields)) {
            json(0, '没有需要优化的字段');
        }
        
        try {
            $fieldsArray = explode(',', $fields);
            $prompt = "基于以下标题和内容，为我生成SEO优化的内容：\n\n标题：{$title}\n\n内容：{$content}\n\n请为以下字段生成内容：" . implode('、', $fieldsArray) . "\n\n要求：\n1. 副标题：简洁有吸引力，不超过30字\n2. URL名称：英文，用连字符分隔，SEO友好\n3. SEO关键字：3-5个相关关键词，用逗号分隔\n4. SEO描述：120-160字符，包含主要关键词\n5. tags：5-8个标签，用逗号分隔\n\n请以JSON格式返回，字段名为：subtitle, urlname, keywords, description, tags";
            
            $result = $this->callAiApi('seoOptimize', $title . "\n\n" . $content, $prompt);
            
            // 尝试解析JSON结果
            $seoData = json_decode($result['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // 如果不是JSON格式，尝试简单解析
                $seoData = $this->parseSeoResult($result['content'], $fieldsArray);
            }
            
            json(1, $seoData, $result['model']);
        } catch (Exception $e) {
            json(0, 'AI SEO优化失败：' . $e->getMessage());
        }
    }
    
    /**
     * 解析SEO优化结果
     */
    private function parseSeoResult($result, $fieldsArray)
    {
        $seoData = [];
        $lines = explode("\n", $result);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // 尝试匹配各种格式
            if (in_array('副标题', $fieldsArray) && (strpos($line, '副标题') !== false || strpos($line, 'subtitle') !== false)) {
                $seoData['subtitle'] = $this->extractContent($line);
            } elseif (in_array('URL名称', $fieldsArray) && (strpos($line, 'URL') !== false || strpos($line, 'urlname') !== false)) {
                $seoData['urlname'] = $this->extractContent($line);
            } elseif (in_array('SEO关键字', $fieldsArray) && (strpos($line, '关键') !== false || strpos($line, 'keywords') !== false)) {
                $seoData['keywords'] = $this->extractContent($line);
            } elseif (in_array('SEO描述', $fieldsArray) && (strpos($line, '描述') !== false || strpos($line, 'description') !== false)) {
                $seoData['description'] = $this->extractContent($line);
            } elseif (in_array('tags', $fieldsArray) && strpos($line, 'tags') !== false) {
                $seoData['tags'] = $this->extractContent($line);
            }
        }
        
        return $seoData;
    }
    
    /**
     * 提取内容
     */
    private function extractContent($line)
    {
        // 移除常见的前缀
        $line = preg_replace('/^[\d\.\-\*\s]*/', '', $line);
        $line = preg_replace('/^(副标题|URL名称|SEO关键字|SEO描述|tags|subtitle|urlname|keywords|description)[:：\s]*/', '', $line);
        return trim($line);
    }

    /**
     * 获取AI配置
     */
    private function getAiConfig()
    {
        return [
            'enabled' => '1', // 直接在类中定义
            'api_url' => Config::get('AiUrl') ?: 'https://api.openai.com/v1/chat/completions',
            'api_key' => Config::get('AiKey') ?: '',
            'model' => Config::get('AiModel') ?: 'gpt-3.5-turbo',
            'timeout' => 600, // 直接在类中定义
            'max_tokens' => 2000, // 直接在类中定义
            'temperature' => 0.3 // 直接在类中定义
        ];
    }
}