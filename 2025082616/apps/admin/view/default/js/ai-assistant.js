/**
 * AI助手功能模块
 * 自动检测表单元素并插入对应的AI功能按钮
 */

// AI助手配置
var AiAssistant = {
    // 忽略列表，这些页面不显示AI助手按钮
    ignoreList: [
        '/Config/',
        '/login',
        '/api/',
        '/Label/',
        '/Model/',
        '/ExtField/',
        '/Site/',
        '/Company/',
        '/ContentSort/',
        '/Slide/',
        '/Link/',
        '/Form/',
        '/Tags/',
        '/Member',
        '/Area/',
        '/Role/',
        '/User/',
        '/Syslog/',
        '/Database/',
        'ajax=1'
    ],

    // 队列管理相关属性
    currentTaskId: null,
    pollingInterval: null,
    pollingDelay: 2000, // 轮询间隔2秒
    maxPollingTime: 300000, // 最大轮询时间5分钟
    pollingStartTime: null,
    processingDialogIndex: null, // 处理中弹窗的索引
    
    /**
     * 获取AI语言参数
     * @returns {string} 选中的AI语言
     */
    getAiLanguage: function() {
        var selectedLang = $('input[name="articleLang"]:checked').val();
        return selectedLang || 'english'; // 默认英语
    },
    
    /**
     * 获取AI语言的中文名称
     * @returns {string} AI语言的中文名称
     */
    getAiLanguageName: function() {
        var langMap = {
            'english': '英语',
            'spanish': '西班牙语',
            'russian': '俄语',
            'french': '法语',
            'arabic': '阿拉伯语',
            'portuguese': '葡萄牙语'
        };
        var selectedLang = this.getAiLanguage();
        return langMap[selectedLang] || '英语';
    },
    
    // 初始化AI助手
    init: function() {
        // 检查当前URL是否在忽略列表中
        var currentUrl = window.location.href;
        for (var i = 0; i < this.ignoreList.length; i++) {
            if (currentUrl.indexOf(this.ignoreList[i]) !== -1) {
                return; // 跳过初始化
            }
        }
        
        // 页面加载时检查是否有未完成的任务
        this.checkExistingTask();
        
        this.insertTitleOptimizeButton();
        this.insertContentButtons();
        this.insertArticleLanguageSelect();
        this.insertSubmitOptimizeButton();
    },

    // 在标题输入框后添加标题优化按钮
    insertTitleOptimizeButton: function() {
        var titleInputs = $('input[name="title"]');
        titleInputs.each(function() {
            var $input = $(this);
            if ($input.next('.ai-title-optimize').length === 0) {
                var optimizeBtn = '<div class="ai-title-optimize">' +
                    '<button type="button" class="layui-btn layui-btn-xs layui-btn-primary" onclick="AiAssistant.optimizeTitleQueue(this)">' +
                    '<i class="layui-icon layui-icon-edit"></i> AI优化（队列）' +
                    '</button>' +
                    '</div>';
                $input.after(optimizeBtn);
            }
        });
    },

    // 在内容区域添加AI功能按钮
    insertContentButtons: function() {
        var $contentElement = $('#editor');
        if ($contentElement.length > 0) {
            var $parentBlock = $contentElement.closest('.layui-input-block');
            // 检查父级是否已经添加了AI按钮
            if ($parentBlock.length > 0 && $parentBlock.find('.ai-content-buttons').length === 0) {
                var buttonsHtml = '<div class="ai-content-buttons">' +
                    '<div class="layui-btn-group">' +
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="AiAssistant.contentOptimizeQueue(this)">' +
                    '<i class="layui-icon layui-icon-edit"></i> 内容优化（队列）' +
                    '</button>' +
                    '<select id="translateLangSelect" class="ai-translate-select">' +
                    '<option value="en" selected>英语</option>' +
                    '<option value="es">西班牙语</option>' +
                    '<option value="ru">俄语</option>' +
                    '<option value="fr">法语</option>' +
                    '<option value="ar">阿拉伯语</option>' +
                    '<option value="pt">葡萄牙语</option>' +
                    '<option value="zh">中文</option>' +
                    '</select>' +
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="AiAssistant.contentTranslateQueue(this)">' +
                    '<i class="layui-icon layui-icon-transfer"></i> 内容翻译（队列）' +
                    '</button>' +
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="AiAssistant.openAiGenerateQueue(this)">' +
                    '<i class="layui-icon layui-icon-add-1"></i> AI代写（队列）' +
                    '</button>' +
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-danger" onclick="AiAssistant.cancelCurrentTask()" style="margin-left: 10px;">' +
                    '<i class="layui-icon layui-icon-close"></i> 取消任务' +
                    '</button>' +
                    '</div>' +
                    '</div>';
                $parentBlock.append(buttonsHtml);
                
                // 渲染新插入的form组件
                if (typeof layui !== 'undefined') {
                    layui.use('form', function(){
                        var form = layui.form;
                        form.render('select');
                    });
                }
            }
        }
    },

    /**
     * 在内容标题上方添加文章语言选择组件
     */
    insertArticleLanguageSelect: function() {
        // 查找内容标题输入框，确保在正确位置插入
        var $titleInput = $('input[name="title"]');
        if ($titleInput.length > 0) {
            var $titleFormItem = $titleInput.closest('.layui-form-item');
            // 检查是否已经添加了文章语言选择组件，并且确保是在内容标题上方
            if ($titleFormItem.length > 0 && $titleFormItem.prev('.ai-article-language').length === 0) {
                var languageHtml = '<div class="layui-form-item ai-article-language">' +
                    '<label class="layui-form-label">AI语言</label>' +
                    '<div class="layui-input-block">' +
                    '<input type="radio" name="articleLang" value="en" title="英语" checked lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="es" title="西班牙语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="ru" title="俄语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="fr" title="法语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="ar" title="阿拉伯语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="pt" title="葡萄牙语" lay-filter="articleLang">' +
                    '</div>' +
                    '</div>';
                $titleFormItem.before(languageHtml);
                
                // 渲染新插入的radio组件
                if (typeof layui !== 'undefined') {
                    layui.use('form', function(){
                        var form = layui.form;
                        form.render('radio');
                    });
                }
            }
        }
    },

    // 在提交按钮前添加AI优化按钮
    insertSubmitOptimizeButton: function() {
        var submitBtns = $('button:contains("立即提交")');
        submitBtns.each(function() {
            var $btn = $(this);
            if ($btn.prev('.ai-optimize-btn').length === 0) {
                var optimizeBtn = '<button type="button" class="layui-btn layui-btn-primary ai-optimize-btn" onclick="AiAssistant.optimizeBeforeSubmit()">' +
                    '<i class="layui-icon layui-icon-edit"></i> AI一键优化' +
                    '</button>';
                $btn.before(optimizeBtn);
            }
        });
    },

    // 标题优化功能
    optimizeTitle: function(btn) {
        var $titleInput = $(btn).closest('.layui-input-block').find('input[name="title"]');

        var currentTitle = $titleInput.val();
        
        if (!currentTitle) {
            layer.msg('请先输入标题');
            return;
        }

        layer.confirm('确定要使用AI优化当前标题吗？', {
            btn: ['确定', '取消']
        }, function(index) {
            layer.close(index);
            
            var loadingIndex = layer.msg('正在优化标题...', {
                icon: 16,
                shade: 0.3,
                time: 0
            });

            $.ajax({
                url: location.pathname + '?p=/Ai/optimizeTitle',
                type: 'POST',
                timeout: 60000000,
                dataType: 'json',
                data: {
                    content: currentTitle,
                    formcheck: $('input[name="formcheck"]').val()
                },
                success: function(response) {
                    layer.close(loadingIndex);
                    if (response.code == 1) {
                        $titleInput.val(response.data);
                        layer.msg('标题优化完成');
                        // 记录优化过的标题
                        $titleInput.data('optimized', true);
                    } else {
                        layer.msg(response.msg || '标题优化失败');
                    }
                },
                error: function() {
                    layer.close(loadingIndex);
                    layer.msg('网络错误，请重试');
                }
            });
        });
    },

    // 内容优化功能
    contentOptimize: function(btn) {
        var $contentElement = $('#editor');
        var $contentBlock = $contentElement.closest('.layui-input-block');
        var editor = this.getEditor($contentBlock);
        var content = this.getContent(editor, $contentBlock);
        if (!content) {
            layer.msg('请先输入内容');
            return;
        }
        
        // 检查内容长度，至少15个字符
        if (content.length < 15) {
            layer.msg('内容长度至少需要15个字符才能进行优化');
            return;
        }

        var loadingIndex = layer.msg('正在优化内容...', {
            icon: 16,
            shade: 0.3,
            time: 0
        });

        $.ajax({
            url: location.pathname + '?p=/Ai/polish',
            type: 'POST',
            timeout: 60000000,
            dataType: 'json',
            data: {
                text: content,
                style: 'professional',
                formcheck: $('input[name="formcheck"]').val()
            },
            success: function(response) {
                layer.close(loadingIndex);
                if (response.code == 1) {
                    var processedResult = AiAssistant.processMarkdownToHtml(response.data);
                    AiAssistant.setContent(editor, $contentBlock, processedResult.content);
                    layer.msg('内容优化完成');
                } else {
                    layer.msg(response.msg || '内容优化失败');
                }
            },
            error: function() {
                layer.close(loadingIndex);
                layer.msg('网络错误，请重试');
            }
        });
    },

    // 内容翻译功能
    contentTranslate: function(btn) {
        var $contentElement = $('#editor');
        var $contentBlock = $contentElement.closest('.layui-input-block');
        var editor = this.getEditor($contentBlock);
        var content = this.getContent(editor, $contentBlock);
        
        if (!content) {
            layer.msg('请先输入内容');
            return;
        }
        
        // 检查内容长度，至少15个字符
        if (content.length < 15) {
            layer.msg('内容长度至少需要15个字符才能进行翻译');
            return;
        }

        // 获取选择的目标语言
        var targetLang = $('#translateLangSelect').val();
        
        var loadingIndex = layer.msg('正在翻译内容...', {
            icon: 16,
            shade: 0.3,
            time: 0
        });

        $.ajax({
            url: location.pathname + '?p=/Ai/translate',
            type: 'POST',
            timeout: 60000000,
            dataType: 'json',
            data: {
                text: content,
                target_lang: targetLang,
                formcheck: $('input[name="formcheck"]').val()
            },
            success: function(response) {
                layer.close(loadingIndex);
                if (response.code == 1) {
                    var processedResult = AiAssistant.processMarkdownToHtml(response.data);
                    AiAssistant.setContent(editor, $contentBlock, processedResult.content);
                    layer.msg('内容翻译完成');
                } else {
                    layer.msg(response.msg || '内容翻译失败');
                }
            },
            error: function() {
                layer.close(loadingIndex);
                layer.msg('网络错误，请重试');
            }
        });
    },

    // AI代写功能
    openAiGenerate: function(btn) {
        var generateHtml = '<div class="layui-form layui-form-pane" style="padding: 20px;">' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">主题：</label>' +
            '<div class="layui-input-block">' +
            '<input type="text" id="generateTopic" placeholder="请输入要生成的内容主题" class="layui-input">' +
            '</div>' +
            '</div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">类型：</label>' +
            '<div class="layui-input-block">' +
            '<input type="radio" name="generateType" value="article" title="文章" checked lay-filter="generateType">' +
            '<input type="radio" name="generateType" value="news" title="新闻" lay-filter="generateType">' +
            '<input type="radio" name="generateType" value="blog" title="博客" lay-filter="generateType">' +
            '<input type="radio" name="generateType" value="product" title="产品介绍" lay-filter="generateType">' +
            '<input type="radio" name="generateType" value="tutorial" title="教程" lay-filter="generateType">' +
            '</div>' +
            '</div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">长度：</label>' +
            '<div class="layui-input-block">' +
            '<input type="radio" name="generateLength" value="short" title="短文(300字)" checked lay-filter="generateLength">' +
            '<input type="radio" name="generateLength" value="medium" title="中等(800字)" lay-filter="generateLength">' +
            '<input type="radio" name="generateLength" value="long" title="长文(1500字)" lay-filter="generateLength">' +
            '</div>' +
            '</div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">语言：</label>' +
            '<div class="layui-input-block">' +
            '<select id="generateLanguage" lay-filter="generateLanguage">' +
            '<option value="en" selected>英语</option>' +
            '<option value="zh">中文</option>' +
            '<option value="es">西班牙语</option>' +
            '<option value="ru">俄语</option>' +
            '<option value="fr">法语</option>' +
            '<option value="ar">阿拉伯语</option>' +
            '<option value="pt">葡萄牙语</option>' +
            '</select>' +
            '</div>' +
            '</div>' +
            '</div>';

        layer.open({
            type: 1,
            title: 'AI代写',
            area: ['800px', '600px'],
            content: generateHtml,
            btn: ['开始生成', '取消'],
            success: function(layero, index) {
                // 初始化layui form组件
                layui.use('form', function(){
                    var form = layui.form;
                    form.render('radio');
                    form.render('select');
                });
            },
            yes: function(index) {
                var topic = $('#generateTopic').val();
                var type = $('input[name="generateType"]:checked').val();
                var length = $('input[name="generateLength"]:checked').val();
                var language = $('#generateLanguage').val();
                
                if (!topic) {
                    layer.msg('请输入主题');
                    return;
                }
                
                layer.close(index);
                
                var loadingIndex = layer.msg('正在生成内容...', {
                    icon: 16,
                    shade: 0.3,
                    time: 0
                });

                $.ajax({
                    url: location.pathname + '?p=/Ai/generate',
                    type: 'POST',
                    timeout: 60000000,
                    dataType: 'json',
                    data: {
                        topic: topic,
                        type: type,
                        length: length,
                        style: 'professional',
                        language: language,
                        formcheck: $('input[name="formcheck"]').val()
                    },
                    success: function(response) {
                        layer.close(loadingIndex);
                        if (response.code == 1) {
                            var processedResult = AiAssistant.processMarkdownToHtml(response.data);
                            var $contentBlock = $(btn).closest('.layui-input-block');
                            var editor = AiAssistant.getEditor($contentBlock);
                            
                            AiAssistant.setContent(editor, $contentBlock, processedResult.content);
                            
                            // 如果有一级标题，插入到页面标题栏
                            if (processedResult.title) {
                                $('input[name="title"]').val(processedResult.title);
                            }
                            
                            layer.msg('内容生成完成');
                        } else {
                            layer.msg(response.msg || '内容生成失败');
                        }
                    },
                    error: function() {
                        layer.close(loadingIndex);
                        layer.msg('网络错误，请重试');
                    }
                });
            }
        });
    },

    // 提交前AI优化
    /**
     * AI一键优化（分步执行版本）
     * 严格按照顺序执行：1.SEO描述 → 2.标题优化 → 3.其他任务
     */
    optimizeBeforeSubmit: function() {
        var self = this;
        
        // 检查是否有正在进行的任务
        if (this.hasActiveTask()) {
            layer.msg('已有AI任务正在进行中，请稍后再试', {icon: 2});
            return;
        }
        
        var content = this.getPageContent();
        var title = $('input[name="title"]').val();
        
        if (!content && !title) {
            layer.msg('未找到标题或内容，无法进行优化', {icon: 2});
            return;
        }
        
        // 第一步任务：通过现有标题获取SEO描述
        var firstStepTasks = [
            { type: 'description', name: 'SEO描述', field: 'textarea[name="description"]', content: title || content }
        ];
        
        // 第二步任务：通过标题和描述获取其他字段（不包括标题优化）
        var secondStepTasks = [
            { type: 'subtitle', name: '副标题生成', field: 'input[name="subtitle"]' },
            { type: 'url', name: 'URL优化', field: 'input[name="filename"]' },
            { type: 'keywords', name: 'SEO关键词', field: 'input[name="keywords"]' },
            { type: 'tags', name: '标签生成', field: 'input[name="tags"]' }
        ];
        
        // 过滤掉页面中不存在的字段
        var availableFirstStepTasks = firstStepTasks.filter(function(task) {
            return $(task.field).length > 0;
        });
        
        var availableSecondStepTasks = secondStepTasks.filter(function(task) {
            return $(task.field).length > 0;
        });
        
        if (availableFirstStepTasks.length === 0 && availableSecondStepTasks.length === 0) {
            layer.msg('未找到可优化的字段', {icon: 2});
            return;
        }
        
        // 合并所有任务用于显示
        var allTasks = availableFirstStepTasks.concat(availableSecondStepTasks);
        
        // 创建进度弹窗HTML
        var progressHtml = '<div class="ai-optimize-progress">' +
            '<div class="progress-header">' +
                '<h3><i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> AI一键优化进行中...</h3>' +
                '<p style="margin: 5px 0; color: #666; font-size: 12px;">分两步执行：第一步生成描述，第二步生成其他字段</p>' +
            '</div>' +
            '<div class="progress-content">';
        
        // 第一步任务显示
        if (availableFirstStepTasks.length > 0) {
            progressHtml += '<div class="step-section">' +
                '<h4 style="margin: 10px 0 5px 0; color: #333; font-size: 14px;">第一步：基于标题生成描述</h4>' +
                '<ul class="task-list">';
            
            availableFirstStepTasks.forEach(function(task) {
                progressHtml += '<li class="task-item" data-task="' + task.type + '" data-step="1">' +
                    '<i class="task-icon layui-icon layui-icon-time"></i>' +
                    '<span class="task-name">' + task.name + '</span>' +
                    '<span class="task-status">等待中</span>' +
                '</li>';
            });
            
            progressHtml += '</ul></div>';
        }
        
        // 第二步任务显示
        if (availableSecondStepTasks.length > 0) {
            progressHtml += '<div class="step-section">' +
                '<h4 style="margin: 10px 0 5px 0; color: #333; font-size: 14px;">第二步：基于标题和描述生成其他字段</h4>' +
                '<ul class="task-list">';
            
            availableSecondStepTasks.forEach(function(task) {
                progressHtml += '<li class="task-item" data-task="' + task.type + '" data-step="2">' +
                    '<i class="task-icon layui-icon layui-icon-time"></i>' +
                    '<span class="task-name">' + task.name + '</span>' +
                    '<span class="task-status">等待中</span>' +
                '</li>';
            });
            
            progressHtml += '</ul></div>';
        }
        
        progressHtml += '</div>' +
            '<div class="progress-footer">' +
                '<button type="button" class="layui-btn layui-btn-sm" onclick="AiAssistant.cancelCurrentTask()">取消</button>' +
            '</div>' +
        '</div>';
        
        // 添加样式
        var style = '<style>' +
            '.ai-optimize-progress { padding: 20px; min-width: 400px; }' +
            '.progress-header h3 { margin: 0 0 10px 0; color: #333; }' +
            '.task-list { list-style: none; padding: 0; margin: 0; }' +
            '.task-item { display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }' +
            '.task-item:last-child { border-bottom: none; }' +
            '.task-icon { margin-right: 10px; width: 16px; color: #999; }' +
            '.task-name { flex: 1; }' +
            '.task-status { color: #999; font-size: 12px; }' +
            '.task-item.running .task-icon { color: #1E9FFF; }' +
            '.task-item.running .task-status { color: #1E9FFF; }' +
            '.task-item.completed .task-icon { color: #5FB878; }' +
            '.task-item.completed .task-status { color: #5FB878; }' +
            '.task-item.error .task-icon { color: #FF5722; }' +
                '.task-item.error .task-status { color: #FF5722; }' +
                '.task-item.skipped .task-icon { color: #FFB800; }' +
                '.task-item.skipped .task-status { color: #FFB800; }' +
            '.progress-footer { margin-top: 20px; text-align: right; }' +
        '</style>';
        
        if (!$('#ai-optimize-style').length) {
            $('head').append('<style id="ai-optimize-style">' + style.replace('<style>', '').replace('</style>', '') + '</style>');
        }
        
        // 显示进度弹窗
        var progressIndex = layer.open({
            type: 1,
            title: false,
            closeBtn: 0,
            area: ['500px', 'auto'],
            content: progressHtml,
            success: function() {
                // 保存任务信息到实例
                self.firstStepTasks = availableFirstStepTasks;
                self.secondStepTasks = availableSecondStepTasks;
                self.originalContent = content;
                self.originalTitle = title;
                
                // 开始两步执行流程
                self.executeTwoStepOptimization();
            }
        });
    },

    /**
     * 执行两步优化流程
     * 第一步：基于标题生成描述
     * 第二步：基于标题和描述生成其他字段
     */
    executeTwoStepOptimization: function() {
        var self = this;
        
        // 初始化上下文
        this.optimizationContext = {
            title: this.originalTitle,
            content: this.originalContent
        };
        
        // 开始第一步：生成描述
        if (this.firstStepTasks && this.firstStepTasks.length > 0) {
            this.executeFirstStep();
        } else {
            // 如果没有第一步任务，直接执行第二步
            this.executeSecondStep();
        }
    },

    /**
     * 执行第一步：基于标题生成描述
     */
    executeFirstStep: function() {
        var self = this;
        
        // 执行第一步任务（描述生成）
        this.executeSequentialOptimization(
            this.originalTitle || this.originalContent, 
            this.firstStepTasks, 
            0, 
            this.optimizationContext,
            {},
            function() {
                // 第一步完成后，执行第二步
                self.executeSecondStep();
            }
        );
    },

    /**
     * 执行第二步：基于标题和描述生成其他字段
     */
    executeSecondStep: function() {
        var self = this;
        
        if (this.secondStepTasks && this.secondStepTasks.length > 0) {
            // 为第二步任务添加content属性
            this.secondStepTasks.forEach(function(task) {
                task.content = self.originalContent;
            });
            
            // 执行第二步任务
            this.executeSequentialOptimization(
                this.originalContent,
                this.secondStepTasks,
                0,
                this.optimizationContext,
                {},
                function() {
                    // 所有任务完成
                    self.completeSequentialOptimization();
                }
            );
        } else {
            // 如果没有第二步任务，直接完成
            this.completeSequentialOptimization();
        }
    },

    /**
     * 分步执行AI优化任务
     * @param {string} content - 页面内容
     * @param {Array} tasks - 任务列表
     * @param {number} currentIndex - 当前任务索引
     * @param {Object} context - 上下文数据（存储之前任务的结果）
     */
    /**
     * 执行顺序优化任务（带重试机制和严格执行顺序）
     * @param {string} content - 内容
     * @param {Array} tasks - 任务列表
     * @param {number} currentIndex - 当前任务索引
     * @param {Object} context - 上下文数据
     * @param {Object} retryCount - 重试计数器对象
     * @param {Function} onComplete - 完成回调函数
     */
    executeSequentialOptimization: function(content, tasks, currentIndex, context, retryCount, onComplete) {
        var self = this;
        
        // 初始化重试计数器
        if (!retryCount) {
            retryCount = {};
        }
        
        // 如果所有任务都完成了
        if (currentIndex >= tasks.length) {
            if (onComplete && typeof onComplete === 'function') {
                onComplete();
            } else {
                self.completeSequentialOptimization();
            }
            return;
        }
        
        var currentTask = tasks[currentIndex];
        var taskKey = currentTask.type + '_' + currentIndex;
        var $taskItem = $('.task-item[data-task="' + currentTask.type + '"]');
        
        // 初始化当前任务的重试计数
        if (!retryCount[taskKey]) {
            retryCount[taskKey] = 0;
        }
        
        // 检查是否为关键任务失败导致的跳过
        if (self.shouldSkipTask(currentTask, context)) {
            // 跳过当前任务，标记为跳过状态
            $taskItem.removeClass('running completed error').addClass('skipped');
            $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop layui-icon-ok layui-icon-close').addClass('layui-icon-pause');
            $taskItem.find('.task-status').text('已跳过');
            
            // 继续下一个任务
            setTimeout(function() {
                self.executeSequentialOptimization(content, tasks, currentIndex + 1, context, retryCount, onComplete);
            }, 200);
            return;
        }
        
        // 更新任务状态为运行中
        var retryText = retryCount[taskKey] > 0 ? '(重试' + retryCount[taskKey] + '/3)' : '';
        $taskItem.removeClass('completed error skipped').addClass('running');
        $taskItem.find('.task-icon').removeClass('layui-icon-time layui-icon-ok layui-icon-close layui-icon-pause').addClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop');
        $taskItem.find('.task-status').text('处理中...' + retryText);
        
        // 根据任务类型构建提示词
        var prompt = self.buildPromptForTask(currentTask, content, context);
        
        // 提交单个任务到队列
        var aiLanguage = self.getAiLanguage();
        self.submitTaskToQueue('ai_generate', {
            topic: currentTask.name,
            requirements: prompt,
            ai_language: aiLanguage
        }, 'ai_generate', 
        function(result) {
            // 任务成功完成
            $taskItem.removeClass('running').addClass('completed');
            $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-ok');
            $taskItem.find('.task-status').text('已完成');
            
            // 保存结果到上下文
            context[currentTask.type] = result;
            
            // 更新对应的表单字段
            self.updateFormField(currentTask, result);
            
            // 继续执行下一个任务
            setTimeout(function() {
                self.executeSequentialOptimization(content, tasks, currentIndex + 1, context, retryCount, onComplete);
            }, 500); // 稍微延迟以避免请求过于频繁
        }, 
        function(error) {
            // 任务失败，检查是否可以重试
            retryCount[taskKey]++;
            
            if (retryCount[taskKey] <= 3) {
                // 还可以重试
                console.log('任务失败，准备重试:', currentTask.name, '重试次数:', retryCount[taskKey]);
                $taskItem.find('.task-status').text('重试中...(第' + retryCount[taskKey] + '次)');
                
                // 延迟后重试当前任务
                setTimeout(function() {
                    self.executeSequentialOptimization(content, tasks, currentIndex, context, retryCount, onComplete);
                }, 1000); // 重试前等待1秒
            } else {
                // 重试次数用完，任务最终失败
                $taskItem.removeClass('running').addClass('error');
                $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
                $taskItem.find('.task-status').text('失败(已重试3次)');
                
                console.error('任务最终失败:', currentTask.name, error);
                
                // 标记关键任务失败
                self.markCriticalTaskFailed(currentTask, context);
                
                // 继续执行下一个任务（但可能会被跳过）
                setTimeout(function() {
                    self.executeSequentialOptimization(content, tasks, currentIndex + 1, context, retryCount, onComplete);
                }, 500);
            }
        });
    },

    /**
     * 检查是否应该跳过当前任务（基于关键任务失败状态）
     * @param {Object} task - 当前任务
     * @param {Object} context - 上下文数据
     * @returns {boolean} 是否应该跳过
     */
    shouldSkipTask: function(task, context) {
        // 如果SEO描述生成失败，跳过标题优化和后续所有任务
        if (context.descriptionFailed && (task.type === 'title' || task.type === 'subtitle' || task.type === 'url' || task.type === 'keywords' || task.type === 'tags')) {
            return true;
        }
        
        // 如果标题优化失败，跳过后续所有任务（除了SEO描述）
        if (context.titleFailed && (task.type === 'subtitle' || task.type === 'url' || task.type === 'keywords' || task.type === 'tags')) {
            return true;
        }
        
        return false;
    },

    /**
     * 标记关键任务失败状态
     * @param {Object} task - 失败的任务
     * @param {Object} context - 上下文数据
     */
    markCriticalTaskFailed: function(task, context) {
        if (task.type === 'description') {
            context.descriptionFailed = true;
            console.log('SEO描述生成失败，后续任务将被跳过');
        } else if (task.type === 'title') {
            context.titleFailed = true;
            console.log('标题优化失败，后续任务将被跳过');
        }
    },

    /**
     * 根据任务类型构建AI提示词
     * @param {Object} task - 当前任务
     * @param {string} content - 原始内容
     * @param {Object} context - 上下文数据
     * @returns {string} 构建的提示词
     */
    buildPromptForTask: function(task, content, context) {
        var currentTitle = $('input[name="title"]').val() || context.title || '未设置';
        var baseContent = '标题：' + currentTitle + '\n\n正文：' + content;
        var aiLanguage = this.getAiLanguageName();
        
        switch(task.type) {
            case 'description':
                // 第一步：仅基于标题生成描述
                if (context.title || $('input[name="title"]').val()) {
                    return '请基于以下标题生成一个SEO友好的描述（150-160字符），直接返回内容不要解释，纯文本不要带格式，以' + aiLanguage + '回复：\n\n标题：' + currentTitle;
                } else {
                    return '请为以下内容生成一个SEO友好的描述（150-160字符），直接返回内容不要解释，纯文本不要带格式，以' + aiLanguage + '回复：\n\n' + baseContent;
                }
                
            case 'title':
                var seoDesc = context.description || '';
                var prompt = '请优化以下标题，使其更具吸引力和SEO友好，直接返回内容不要解释，纯文本不要带格式，以' + aiLanguage + '回复：\n\n' + baseContent;
                if (seoDesc) {
                    prompt += '\n\n参考SEO描述：' + seoDesc;
                }
                return prompt;
                
            case 'subtitle':
                var seoDesc = context.description || '';
                var prompt = '请为以下内容生成副标题，直接返回内容不要解释，纯文本不要带格式，以' + aiLanguage + '回复：\n\n标题：' + currentTitle;
                if (seoDesc) {
                    prompt += '\n\nSEO描述：' + seoDesc;
                }
                return prompt;
                
            case 'url':
                var seoDesc = context.description || '';
                var prompt = '请为以下内容，生成SEO友好的URL（英文，用连字符分隔）直接返回内容不要解释：\n\n标题：' + currentTitle;
                if (seoDesc) {
                    prompt += '\n\nSEO描述：' + seoDesc;
                }
                return prompt;
                
            case 'keywords':
                var seoDesc = context.description || '';
                var prompt = '请为以下内容，生成SEO关键词（英文，逗号分隔），直接返回内容不要解释，纯文本不要带格式，以' + aiLanguage + '回复：\n\n标题：' + currentTitle;
                if (seoDesc) {
                    prompt += '\n\nSEO描述：' + seoDesc;
                }
                return prompt;
                
            case 'tags':
                var seoDesc = context.description || '';
                var prompt = '请为以下内容生成标签（英文，逗号分隔）直接返回内容不要解释，纯文本不要带格式：\n\n标题：' + currentTitle;
                if (seoDesc) {
                    prompt += '\n\nSEO描述：' + seoDesc;
                }
                return prompt;
                
            default:
                return baseContent;
        }
    },

    /**
     * 更新表单字段
     * @param {Object} task - 任务信息
     * @param {string} result - AI生成的结果
     */
    updateFormField: function(task, result) {
        var $field = $(task.field);
        if ($field.length > 0) {
            // 对于标题字段，需要特殊处理（可能有编辑和新增两种情况）
            if (task.type === 'title') {
                var $titleInput = $('#content-title-edit');
                if ($titleInput.length === 0) {
                    $titleInput = $('#content-title-add');
                }
                if ($titleInput.length === 0) {
                    $titleInput = $('input[name="title"]');
                }
                if ($titleInput.length > 0) {
                    $titleInput.val(result);
                    $titleInput.trigger('change');
                }
            } else {
                $field.val(result);
                $field.trigger('change');
            }
        }
    },

    /**
     * 完成所有优化任务（包含重试和跳过统计）
     */
    completeSequentialOptimization: function() {
        var completedCount = $('.task-item.completed').length;
        var errorCount = $('.task-item.error').length;
        var skippedCount = $('.task-item.skipped').length;
        var totalCount = $('.task-item').length;
        
        var message = 'AI一键优化完成！';
        var details = [];
        
        if (completedCount > 0) {
            details.push('成功：' + completedCount + '个');
        }
        if (errorCount > 0) {
            details.push('失败：' + errorCount + '个');
        }
        if (skippedCount > 0) {
            details.push('跳过：' + skippedCount + '个');
        }
        
        if (details.length > 0) {
            message += details.join('，');
        } else {
            message += '无任务执行';
        }
        
        // 根据结果选择合适的图标
        var icon = 1; // 默认成功图标
        if (errorCount > 0 && completedCount === 0) {
            icon = 2; // 全部失败用错误图标
        } else if (errorCount > 0 || skippedCount > 0) {
            icon = 6; // 部分成功用警告图标
        }
        
        layer.msg(message, {icon: icon, time: 4000});
        
        // 延迟关闭弹窗，让用户看到最终结果
        setTimeout(function() {
            layer.closeAll();
        }, 2000);
    },
    
    // 执行优化任务
    executeOptimizeTasks: function(tasks, currentIndex) {
        var self = this;
        
        if (currentIndex >= tasks.length) {
            // 所有任务完成
            $('.progress-header h3').html('<i class="layui-icon layui-icon-ok"></i> 优化完成！');
            $('.progress-footer').html('<button type="button" class="layui-btn layui-btn-normal" onclick="layer.closeAll()">完成</button>');
            return;
        }
        
        // 按照新的优化逻辑执行
        if (currentIndex === 0) {
            // 第一步：生成SEO描述
            self.executeStepByStep();
        } else {
            // 兼容旧逻辑，处理剩余任务
            self.executeRemainingTasks(tasks, currentIndex);
        }
    },
    
    // 按步骤执行优化
    executeStepByStep: function() {
        var self = this;
        
        // 步骤1：先生成SEO描述
        self.generateSeoDescription(function(success, seoDescription) {
            if (success) {
                // 步骤2：使用标题+SEO描述优化标题
                self.optimizeTitleWithSeo(seoDescription, function(success, optimizedTitle) {
                    if (success) {
                        // 步骤3：使用优化后的标题+SEO描述完成后续优化
                        self.executeRemainingOptimizations(optimizedTitle, seoDescription);
                    } else {
                        self.completeAllTasks();
                    }
                });
            } else {
                // 如果SEO描述生成失败，继续执行其他任务
                self.executeRemainingOptimizations('', '');
            }
        });
    },
    
    // 生成SEO描述
    generateSeoDescription: function(callback) {
        var self = this;
        var titleContent = $('input[name="title"]').val();
        var content = self.getPageContent();
        var $descField = $('textarea[name="description"]');
        var $taskItem = $('.task-item[data-task="description"]');
        
        // 更新任务状态
        $taskItem.removeClass('pending completed error').addClass('running');
        $taskItem.find('.task-icon').removeClass('layui-icon-time layui-icon-ok layui-icon-close').addClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop');
        $taskItem.find('.task-status').text('进行中...');
        
        if (!titleContent && !content) {
            $taskItem.removeClass('running').addClass('error');
            $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
            $taskItem.find('.task-status').text('失败: 缺少内容源');
            callback(false, '');
            return;
        }
        
        var sourceContent = (titleContent || '') + '\n\n' + (content || '');
        
        $.ajax({
            url: location.pathname + '?p=/Ai/optimize',
            type: 'POST',
            timeout: 6000000,
            dataType: 'json',
            data: {
                type: 'description',
                content: sourceContent,
                formcheck: $('input[name="formcheck"]').val()
            },
            success: function(response) {
                if (response.code == 1) {
                    if ($descField.length > 0) {
                        $descField.val(response.data);
                    }
                    $taskItem.removeClass('running').addClass('completed');
                    $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-ok');
                    $taskItem.find('.task-status').text('已完成');
                    callback(true, response.data);
                } else {
                    $taskItem.removeClass('running').addClass('error');
                    $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
                    $taskItem.find('.task-status').text('失败: ' + (response.msg || 'SEO描述生成失败'));
                    callback(false, '');
                }
            },
            error: function() {
                $taskItem.removeClass('running').addClass('error');
                $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
                $taskItem.find('.task-status').text('失败: 网络错误');
                callback(false, '');
            }
        });
    },
    
    // 使用SEO描述优化标题
    optimizeTitleWithSeo: function(seoDescription, callback) {
        var self = this;
        var $titleInput = $('input[name="title"]');
        var currentTitle = $titleInput.val();
        var $taskItem = $('.task-item[data-task="title"]');
        
        // 更新任务状态
        $taskItem.removeClass('pending completed error').addClass('running');
        $taskItem.find('.task-icon').removeClass('layui-icon-time layui-icon-ok layui-icon-close').addClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop');
        $taskItem.find('.task-status').text('进行中...');
        
        if (!currentTitle) {
            $taskItem.removeClass('running').addClass('error');
            $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
            $taskItem.find('.task-status').text('失败: 标题为空');
            callback(false, currentTitle);
            return;
        }
        
        // 检查标题是否已经优化过
        if ($titleInput.data('optimized')) {
            $taskItem.removeClass('running').addClass('completed');
            $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-ok');
            $taskItem.find('.task-status').text('已完成');
            callback(true, currentTitle);
            return;
        }
        
        var sourceContent = currentTitle + '\n\n' + seoDescription;
        
        $.ajax({
            url: location.pathname + '?p=/Ai/optimize',
            type: 'POST',
            timeout: 6000000,
            dataType: 'json',
            data: {
                type: 'title',
                content: sourceContent,
                formcheck: $('input[name="formcheck"]').val()
            },
            success: function(response) {
                if (response.code == 1) {
                    $titleInput.val(response.data);
                    $titleInput.data('optimized', true);
                    $taskItem.removeClass('running').addClass('completed');
                    $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-ok');
                    $taskItem.find('.task-status').text('已完成');
                    callback(true, response.data);
                } else {
                    $taskItem.removeClass('running').addClass('error');
                    $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
                    $taskItem.find('.task-status').text('失败: ' + (response.msg || '标题优化失败'));
                    callback(false, currentTitle);
                }
            },
            error: function() {
                $taskItem.removeClass('running').addClass('error');
                $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
                $taskItem.find('.task-status').text('失败: 网络错误');
                callback(false, currentTitle);
            }
        });
    },
    
    // 执行剩余的优化任务
    executeRemainingOptimizations: function(optimizedTitle, seoDescription) {
        var self = this;
        var remainingTasks = [
            { id: 'subtitle', name: '副标题生成', field: 'input[name="subtitle"]' },
            { id: 'url', name: 'URL优化', field: 'input[name="filename"]' },
            { id: 'keywords', name: 'SEO关键词', field: 'input[name="keywords"]' },
            { id: 'tags', name: '标签生成', field: 'input[name="tags"]' }
        ];
        
        self.executeRemainingTasksSequentially(remainingTasks, 0, optimizedTitle, seoDescription);
    },
    
    // 顺序执行剩余任务
    executeRemainingTasksSequentially: function(tasks, currentIndex, optimizedTitle, seoDescription) {
        var self = this;
        
        if (currentIndex >= tasks.length) {
            self.completeAllTasks();
            return;
        }
        
        var task = tasks[currentIndex];
        var $field = $(task.field);
        var $taskItem = $('.task-item[data-task="' + task.id + '"]');
        
        // 检查字段是否存在
        if ($field.length === 0) {
            $taskItem.removeClass('pending running error').addClass('completed');
            $taskItem.find('.task-icon').removeClass('layui-icon-time layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-ok');
            $taskItem.find('.task-status').text('已跳过');
            
            setTimeout(function() {
                self.executeRemainingTasksSequentially(tasks, currentIndex + 1, optimizedTitle, seoDescription);
            }, 500);
            return;
        }
        
        // 更新任务状态
        $taskItem.removeClass('pending completed error').addClass('running');
        $taskItem.find('.task-icon').removeClass('layui-icon-time layui-icon-ok layui-icon-close').addClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop');
        $taskItem.find('.task-status').text('进行中...');
        
        var sourceContent = (optimizedTitle || '') + '\n\n' + (seoDescription || '');
        
        $.ajax({
            url: location.pathname + '?p=/Ai/optimize',
            type: 'POST',
            timeout: 6000000,
            dataType: 'json',
            data: {
                type: task.id,
                content: sourceContent,
                formcheck: $('input[name="formcheck"]').val()
            },
            success: function(response) {
                if (response.code == 1) {
                    $field.val(response.data);
                    $taskItem.removeClass('running').addClass('completed');
                    $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-ok');
                    $taskItem.find('.task-status').text('已完成');
                } else {
                    $taskItem.removeClass('running').addClass('error');
                    $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
                    $taskItem.find('.task-status').text('失败: ' + (response.msg || task.name + '生成失败'));
                }
                
                setTimeout(function() {
                    self.executeRemainingTasksSequentially(tasks, currentIndex + 1, optimizedTitle, seoDescription);
                }, 1000);
            },
            error: function() {
                $taskItem.removeClass('running').addClass('error');
                $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
                $taskItem.find('.task-status').text('失败: 网络错误');
                
                setTimeout(function() {
                    self.executeRemainingTasksSequentially(tasks, currentIndex + 1, optimizedTitle, seoDescription);
                }, 1000);
            }
        });
    },
    
    // 完成所有任务
    completeAllTasks: function() {
        $('.progress-header h3').html('<i class="layui-icon layui-icon-ok"></i> 优化完成！');
        $('.progress-footer').html('<button type="button" class="layui-btn layui-btn-normal" onclick="layer.closeAll()">完成</button>');
    },
    
    // 兼容旧逻辑，处理剩余任务
    executeRemainingTasks: function(tasks, currentIndex) {
        var self = this;
        
        if (currentIndex >= tasks.length) {
            self.completeAllTasks();
            return;
        }
        
        var task = tasks[currentIndex];
        var $taskItem = $('.task-item[data-task="' + task.id + '"]');
        
        // 更新任务状态为进行中
        $taskItem.removeClass('pending completed error').addClass('running');
        $taskItem.find('.task-icon').removeClass('layui-icon-time layui-icon-ok layui-icon-close').addClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop');
        $taskItem.find('.task-status').text('进行中...');
        
        // 检查字段是否存在
        var $field = $(task.field);
        if ($field.length === 0) {
            // 字段不存在，跳过
            $taskItem.removeClass('running').addClass('completed');
            $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-ok');
            $taskItem.find('.task-status').text('已跳过');
            
            setTimeout(function() {
                self.executeRemainingTasks(tasks, currentIndex + 1);
            }, 500);
            return;
        }
        
        // 执行具体的优化任务
        if (task.id === 'title') {
            self.optimizeTitleTask($field, function(success, message, model) {
                self.handleTaskResult(task, $taskItem, success, message, tasks, currentIndex, model);
            });
        } else {
            self.optimizeFieldTask(task, $field, function(success, message, model) {
                self.handleTaskResult(task, $taskItem, success, message, tasks, currentIndex, model);
            });
        }
    },
    
    // 处理任务结果
    handleTaskResult: function(task, $taskItem, success, message, tasks, currentIndex, model) {
        var self = this;
        
        if (success) {
            $taskItem.removeClass('running').addClass('completed');
            $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-ok');
            var statusText = '已完成';
            if (model) {
                statusText += ' (' + model + ')';
            }
            $taskItem.find('.task-status').text(statusText);
        } else {
            $taskItem.removeClass('running').addClass('error');
            $taskItem.find('.task-icon').removeClass('layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop').addClass('layui-icon-close');
            $taskItem.find('.task-status').text('失败: ' + (message || '未知错误'));
        }
        
        // 继续下一个任务
        setTimeout(function() {
            self.executeOptimizeTasks(tasks, currentIndex + 1);
        }, 1000);
    },
    
    // 优化标题任务
    optimizeTitleTask: function($titleInput, callback) {
        var currentTitle = $titleInput.val();
        
        // 检查标题是否已经优化过
        if ($titleInput.data('optimized')) {
            callback(true, '标题已优化过');
            return;
        }
        
        if (!currentTitle) {
            callback(false, '标题为空');
            return;
        }
        
        $.ajax({
            url: location.pathname + '?p=/Ai/optimizeTitle',
            type: 'POST',
            timeout: 6000000,
            dataType: 'json',
            data: {
                content: currentTitle,
                formcheck: $('input[name="formcheck"]').val()
            },
            success: function(response) {
                if (response.code == 1) {
                    $titleInput.val(response.data);
                    $titleInput.data('optimized', true);
                    var model = response.tourl || null;
                    callback(true, '标题优化成功', model);
                } else {
                    callback(false, response.msg || '标题优化失败');
                }
            },
            error: function() {
                callback(false, '网络错误');
            }
        });
    },
    
    // 优化其他字段任务
    optimizeFieldTask: function(task, $field, callback) {
        var self = this;
        var titleContent = $('input[name="title"]').val();
        var content = self.getPageContent(); // 获取页面内容
        
        if (!titleContent && !content) {
            callback(false, '缺少内容源');
            return;
        }
        
        var sourceContent = titleContent || content;
        
        $.ajax({
            url: location.pathname + '?p=/Ai/optimize',
            type: 'POST',
            timeout: 6000000,
            dataType: 'json',
            data: {
                type: task.id,
                content: sourceContent,
                formcheck: $('input[name="formcheck"]').val()
            },
            success: function(response) {
                if (response.code == 1) {
                    $field.val(response.data);
                    var model = response.tourl || null;
                    callback(true, task.name + '生成成功', model);
                } else {
                    callback(false, response.msg || task.name + '生成失败');
                }
            },
            error: function() {
                callback(false, '网络错误');
            }
        });
    },
    
    // 获取页面内容
    getPageContent: function() {
        // 尝试从编辑器获取内容
        var content = '';
        
        // UEditor
        if (typeof UE !== 'undefined' && UE.getEditor('editor')) {
            content = UE.getEditor('editor').getContent();
        }
        // 普通textarea
        else if ($('textarea[name="content"]').length) {
            content = $('textarea[name="content"]').val();
        }
        
        // 清理HTML标签，只保留文本内容
        if (content) {
            content = content.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
        }
        
        return content;
    },

    // 获取编辑器实例
    getEditor: function($contentBlock) {
        // UEditor实例存储在UE.instants中，通常为ueditorInstant0
        // 先尝试获取第一个可用的编辑器实例
        if (typeof UE !== 'undefined' && UE.instants) {
            for (var key in UE.instants) {
                if (key.indexOf('ueditorInstant') === 0) {
                    var editor = UE.instants[key];
                    if (editor && editor.ready) {
                        return editor;
                    }
                }
            }
        }
        
        // 如果没找到ready的编辑器，尝试通过UE.getEditor获取
        if (typeof UE !== 'undefined' && UE.getEditor) {
            return UE.getEditor('editor');
        }
        
        return null;
    },

    // 获取内容
    getContent: function(editor, $contentBlock) {
        if (editor) {
            return editor.getContent();
        }
        
        var textarea = $contentBlock.find('textarea');
        if (textarea.length > 0) {
            return textarea.val();
        }
        
        return '';
    },

    // 设置内容
    setContent: function(editor, $contentBlock, content) {
        if (editor) {
            editor.setContent(content);
            return;
        }
        
        var textarea = $contentBlock.find('textarea');
        if (textarea.length > 0) {
            textarea.val(content);
            return;
        }
    },

    // Markdown转HTML处理函数
    processMarkdownToHtml: function(markdown) {
        var title = '';
        var content = markdown;
        
        // 1. 提取一级标题
        var h1Match = content.match(/^#\s+(.+)$/m);
        if (h1Match) {
            title = h1Match[1].trim();
            // 移除一级标题，避免重复
            content = content.replace(/^#\s+.+$/m, '').trim();
        }
        
        // 2. 转换二级到六级标题
        content = content.replace(/^######\s+(.+)$/gm, '<h6>$1</h6>');
        content = content.replace(/^#####\s+(.+)$/gm, '<h5>$1</h5>');
        content = content.replace(/^####\s+(.+)$/gm, '<h4>$1</h4>');
        content = content.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');
        content = content.replace(/^##\s+(.+)$/gm, '<h2>$1</h2>');
        
        // 3. 转换加粗格式
        content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        
        // 4. 处理换行，将双换行或单换行替换为<p>标签
        // 先分割段落（双换行分隔）
        var paragraphs = content.split(/\n\s*\n/);
        var htmlParagraphs = [];
        
        for (var i = 0; i < paragraphs.length; i++) {
            var paragraph = paragraphs[i].trim();
            if (paragraph) {
                // 处理段落内的单换行，替换为<br>
                paragraph = paragraph.replace(/\n/g, '<br>');
                // 如果不是标题标签，包装为<p>标签
                if (!paragraph.match(/^<h[2-6]>/)) {
                    paragraph = '<p>' + paragraph + '</p>';
                }
                htmlParagraphs.push(paragraph);
            }
        }
        
        content = htmlParagraphs.join('\n');
        
        return {
            title: title,
            content: content
        };
    },

    // ==================== 队列管理相关方法 ====================

    /**
     * 检查页面加载时是否有未完成的任务
     */
    checkExistingTask: function() {
        var taskId = localStorage.getItem('ai_task_id');
        var taskType = localStorage.getItem('ai_task_type');
        
        if (taskId && taskType) {
            this.currentTaskId = taskId;
            
            // 根据任务类型重新创建成功回调函数
            var successCallback = this.createCallbackByType(taskType);
            this.checkTaskStatus(taskId, true, successCallback);
        }
    },

    /**
     * 检查是否有正在进行的任务（防止冲突）
     */
    hasActiveTask: function() {
        return this.currentTaskId !== null && localStorage.getItem('ai_task_id') !== null;
    },

    /**
     * 根据任务类型创建对应的成功回调函数
     */
    createCallbackByType: function(taskType) {
        var self = this;
        switch(taskType) {
            case 'optimize_title':
                return function(result) {
                    // 优先选择编辑页面的标题输入框，如果不存在则选择新增页面的
                    var $titleInput = $('#content-title-edit');
                    if ($titleInput.length === 0) {
                        $titleInput = $('#content-title-add');
                    }
                    
                    if ($titleInput.length > 0) {
                        $titleInput.val(result);
                        // 触发change事件以确保表单状态更新
                        $titleInput.trigger('change');
                        layer.msg('标题优化完成');
                    }
                };
                break;
            case 'content_optimize':
                return function(result) {
                    var processedResult = self.processMarkdownToHtml(result);
                    var $contentBlock = $('.layui-input-block').has('textarea[name="content"]');
                    var editor = self.getEditor($contentBlock);
                    self.setContent(editor, $contentBlock, processedResult.content);
                    layer.msg('内容优化完成');
                };
                break;
            case 'content_translate':
                return function(result) {
                    var processedResult = self.processMarkdownToHtml(result);
                    var $contentBlock = $('.layui-input-block').has('textarea[name="content"]');
                    var editor = self.getEditor($contentBlock);
                    self.setContent(editor, $contentBlock, processedResult.content);
                    layer.msg('内容翻译完成');
                };
                break;
            case 'ai_generate':
                return function(result) {
                    var processedResult = self.processMarkdownToHtml(result);
                    var $contentBlock = $('.layui-input-block').has('textarea[name="content"]');
                    var editor = self.getEditor($contentBlock);
                    
                    self.setContent(editor, $contentBlock, processedResult.content);
                    
                    if (processedResult.title) {
                        // 优先选择编辑页面的标题输入框，如果不存在则选择新增页面的
                        var $titleInput = $('#content-title-edit');
                        if ($titleInput.length === 0) {
                            $titleInput = $('#content-title-add');
                        }
                        if ($titleInput.length > 0) {
                            $titleInput.val(processedResult.title);
                            $titleInput.trigger('change');
                        }
                    }
                    
                    layer.msg('内容生成完成');
                };
                break;
            default:
                return function(result) {
                    layer.msg('任务已完成');
                };
        }
    },

    /**
     * 提交任务到队列
     */
    submitTaskToQueue: function(type, data, taskType, successCallback, errorCallback) {
        var self = this;
        
        // 检查是否有正在进行的任务
        if (this.hasActiveTask()) {
            layer.msg('当前有任务正在处理中，请等待完成后再试');
            return;
        }

        $.ajax({
            url: location.pathname + '?p=/Index/aiQueueSubmit',
            type: 'POST',
            dataType: 'json',
            data: {
                type: type,
                data: data,
                formcheck: $('input[name="formcheck"]').val()
            },
            success: function(response) {
                if (response.code == 1) {
                    var taskId = response.data.task_id;
                    self.currentTaskId = taskId;
                    localStorage.setItem('ai_task_id', taskId);
                    localStorage.setItem('ai_task_type', taskType);
                    

                    
                    // 开始轮询
                    self.startPolling(taskId, successCallback, errorCallback);
                } else {
                    if (errorCallback) errorCallback(response.msg || '任务提交失败');
                }
            },
            error: function() {
                if (errorCallback) errorCallback('网络错误，请重试');
            }
        });
    },

    /**
     * 开始轮询任务状态
     */
    startPolling: function(taskId, successCallback, errorCallback) {
        var self = this;
        
        this.pollingStartTime = Date.now();
        
        // 显示轮询状态
        var loadingIndex = layer.msg('任务处理中，请稍候...', {
            icon: 16,
            shade: 0.3,
            time: 0
        });
        
        // 保存弹窗索引以便后续关闭
        this.processingDialogIndex = loadingIndex;
        
        this.pollingInterval = setInterval(function() {
            var currentTime = Date.now();
            var elapsedTime = currentTime - self.pollingStartTime;
            
            // 检查是否超时
            if (elapsedTime > self.maxPollingTime) {
                self.stopPolling();
                layer.close(loadingIndex);
                if (errorCallback) {
                    errorCallback('任务处理超时，请稍后查看结果');
                }
                return;
            }
            
            self.checkTaskStatus(taskId, false, function(result) {
                // 任务完成
                self.stopPolling();
                layer.close(loadingIndex);
                if (successCallback) {
                    successCallback(result);
                }
            }, function(error) {
                // 任务失败
                self.stopPolling();
                layer.close(loadingIndex);
                if (errorCallback) {
                    errorCallback(error);
                }
            });
        }, this.pollingDelay);
        

    },

    /**
     * 检查任务状态
     */
    checkTaskStatus: function(taskId, isPageLoad, successCallback, errorCallback) {
        var self = this;
        
        $.ajax({
            url: location.pathname + '?p=/Index/aiQueueStatus',
            type: 'GET',
            dataType: 'json',
            data: {
                task_id: taskId
            },
            success: function(response) {
                // 处理不同的状态码
                if (response.code == 3) {
                    // 任务已完成
                    self.clearTask();
                    self.closeProcessingDialog(); // 关闭处理中的弹窗
                    
                    if (successCallback) {
                        try {
                            successCallback(response.data.result);
                        } catch (e) {
                            console.error('执行成功回调函数时出错:', e);
                        }
                    } else if (isPageLoad) {
                        layer.msg('之前的AI任务已完成');
                    }
                } else if (response.code == 4) {
                    // 任务失败
                    self.clearTask();
                    self.closeProcessingDialog(); // 关闭处理中的弹窗
                    
                    var error = response.data.error || response.msg || '任务处理失败';
                    if (errorCallback) {
                        errorCallback(error);
                    } else if (isPageLoad) {
                        layer.msg('之前的AI任务失败: ' + error);
                    }
                } else if (response.code == 2) {
                    // 任务不存在或已过期
                    self.clearTask();
                    self.closeProcessingDialog(); // 关闭处理中的弹窗
                    
                    if (errorCallback) {
                        errorCallback(response.msg || '任务不存在或已过期');
                    } else if (isPageLoad) {
                        layer.msg('之前的AI任务不存在或已过期');
                    }
                } else if (response.code == 5) {
                    // 任务已取消
                    self.clearTask();
                    self.closeProcessingDialog(); // 关闭处理中的弹窗
                    
                    if (errorCallback) {
                        errorCallback(response.msg || '任务已取消');
                    } else if (isPageLoad) {
                        layer.msg('之前的AI任务已取消');
                    }
                } else if (response.code == 1) {
                    // 任务进行中（pending或processing状态）
                    var taskStatus = response.data.task_status;
                    var retryCount = response.data.retry_count || 0;
                    var statusMessage = '检测到未完成的AI任务，继续等待处理...';
                    
                    if (retryCount > 0) {
                        statusMessage = '任务正在重试中（第' + retryCount + '次重试），请耐心等待...';
                    }
                    
                    if (isPageLoad) {
                        // 页面刷新后恢复轮询
                        layer.msg(statusMessage);
                        self.startPolling(taskId, successCallback, errorCallback);
                    }
                    // 继续轮询，不做其他操作
                } else {
                    // 其他错误情况
                    self.clearTask();
                    self.closeProcessingDialog(); // 关闭处理中的弹窗
                    
                    if (errorCallback) {
                        errorCallback(response.msg || '任务查询失败');
                    }
                }
            },
            error: function(xhr, status, error) {
                // 网络错误也需要清理任务并关闭弹窗
                self.clearTask();
                self.closeProcessingDialog();
                
                if (errorCallback) {
                    errorCallback('网络错误，请重试');
                }
            }
        });
    },

    /**
     * 停止轮询
     */
    stopPolling: function() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        this.pollingStartTime = null;
        // 清理弹窗索引
        this.processingDialogIndex = null;
    },

    /**
     * 清除当前任务
     */
    clearTask: function() {
        this.currentTaskId = null;
        localStorage.removeItem('ai_task_id');
        localStorage.removeItem('ai_task_type');
        
        this.stopPolling();
    },

    /**
     * 关闭处理中的弹窗
     */
    closeProcessingDialog: function() {
        // 关闭所有loading类型的弹窗
        layer.closeAll('loading');
        // 如果有特定的弹窗索引，也可以关闭
        if (this.processingDialogIndex) {
            layer.close(this.processingDialogIndex);
            this.processingDialogIndex = null;
        }
    },

    /**
     * 取消当前任务
     */
    cancelCurrentTask: function() {
        var self = this;
        if (!this.currentTaskId) {
            layer.msg('没有正在进行的任务');
            layer.close(this);
            return;
        }
        
        layer.confirm('确定要取消当前AI任务吗？', {
            icon: 3,
            title: '确认取消'
        }, function(index) {
            $.ajax({
                url: location.pathname + '?p=/Index/aiQueueCancel',
                type: 'POST',
                dataType: 'json',
                data: {
                    task_id: self.currentTaskId,
                    formcheck: $('input[name="formcheck"]').val()
                },
                success: function(response) {
                    if (response.code == 1) {
                        self.clearTask();
                        layer.msg('任务已取消');
                    } else {
                        layer.msg(response.msg || '取消失败');
                    }
                },
                error: function() {
                    layer.msg('网络错误，请重试');
                }
            });
            layer.close(index);
        });
    },

    // ==================== 修改现有方法以支持队列 ====================

    /**
     * 队列版本的标题优化
     */
    optimizeTitleQueue: function(btn) {
        var $titleInput = $(btn).closest('.layui-input-block').find('input[name="title"]');
        var title = $titleInput.val();
        
        if (!title) {
            layer.msg('请先输入标题');
            return;
        }
        
        var self = this;
        var aiLanguage = this.getAiLanguage();
        this.submitTaskToQueue('optimize_title', {
            content: title,
            ai_language: aiLanguage
        }, 'optimize_title', function(result) {
            $titleInput.val(result);
            $titleInput.trigger('change');
            layer.msg('标题优化完成');
        }, function(error) {
            layer.msg(error);
        });
    },

    /**
     * 队列版本的内容润色
     */
    contentOptimizeQueue: function(btn) {
        var $contentElement = $('#editor');
        var $contentBlock = $contentElement.closest('.layui-input-block');
        var editor = this.getEditor($contentBlock);
        var content = this.getContent(editor, $contentBlock);
        
        if (!content) {
            layer.msg('请先输入内容');
            return;
        }
        
        var self = this;
        var aiLanguage = this.getAiLanguage();
        this.submitTaskToQueue('content_polish', {
            text: content,
            style: 'professional',
            ai_language: aiLanguage
        }, 'content_optimize', function(result) {
            var processedResult = self.processMarkdownToHtml(result);
            self.setContent(editor, $contentBlock, processedResult.content);
            layer.msg('内容润色完成');
        }, function(error) {
            layer.msg(error);
        });
    },

    /**
     * 队列版本的内容翻译
     */
    contentTranslateQueue: function(btn) {
        var $contentElement = $('#editor');
        var $contentBlock = $contentElement.closest('.layui-input-block');
        var editor = this.getEditor($contentBlock);
        var content = this.getContent(editor, $contentBlock);
        
        if (!content) {
            layer.msg('请先输入内容');
            return;
        }
        
        if (content.length < 15) {
            layer.msg('内容长度至少需要15个字符才能进行翻译');
            return;
        }
        
        var targetLang = $('#translateLangSelect').val();
        var self = this;
        
        this.submitTaskToQueue('content_translate', {
            text: content,
            target_lang: targetLang
        }, 'content_translate', function(result) {
            var processedResult = self.processMarkdownToHtml(result);
            self.setContent(editor, $contentBlock, processedResult.content);
            layer.msg('内容翻译完成');
        }, function(error) {
            layer.msg(error);
        });
    },

    /**
     * 处理AI一键优化的批量结果
     */
    processBatchOptimizeResults: function(results) {
        var self = this;
        var successCount = 0;
        var failCount = 0;
        
        if (results && typeof results === 'object') {
            for (var taskType in results) {
                var result = results[taskType];
                if (result.success) {
                    successCount++;
                    // 根据任务类型更新对应的字段
                    switch(taskType) {
                        case 'title':
                            var $titleInput = $('input[name="title"]');
                            if ($titleInput.length > 0) {
                                $titleInput.val(result.data);
                            }
                            break;
                        case 'subtitle':
                            var $subtitleInput = $('input[name="subtitle"]');
                            if ($subtitleInput.length > 0) {
                                $subtitleInput.val(result.data);
                            }
                            break;
                        case 'url':
                            var $urlInput = $('input[name="url"]');
                            if ($urlInput.length > 0) {
                                $urlInput.val(result.data);
                            }
                            break;
                        case 'keywords':
                            var $keywordsInput = $('input[name="keywords"]');
                            if ($keywordsInput.length > 0) {
                                $keywordsInput.val(result.data);
                            }
                            break;
                        case 'description':
                            var $descInput = $('textarea[name="description"]');
                            if ($descInput.length > 0) {
                                $descInput.val(result.data);
                            }
                            break;
                        case 'tags':
                            var $tagsInput = $('input[name="tags"]');
                            if ($tagsInput.length > 0) {
                                $tagsInput.val(result.data);
                            }
                            break;
                    }
                } else {
                    failCount++;
                    console.log('任务 ' + taskType + ' 失败: ' + result.error);
                }
            }
        }
        
        // 显示结果统计
        if (successCount > 0 && failCount === 0) {
            layer.msg('AI一键优化完成！成功优化了 ' + successCount + ' 个字段', {icon: 1});
        } else if (successCount > 0 && failCount > 0) {
            layer.msg('AI一键优化部分完成！成功 ' + successCount + ' 个，失败 ' + failCount + ' 个', {icon: 2});
        } else {
            layer.msg('AI一键优化失败，请稍后重试', {icon: 2});
        }
    },

    /**
     * 队列版本的AI代写
     */
    openAiGenerateQueue: function(btn) {
        var self = this;
        var generateHtml = '<div class="layui-form layui-form-pane" style="padding: 20px;">' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">主题：</label>' +
            '<div class="layui-input-block">' +
            '<input type="text" id="generateTopic" placeholder="请输入要生成的内容主题" class="layui-input">' +
            '</div>' +
            '</div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">要求：</label>' +
            '<div class="layui-input-block">' +
            '<textarea id="generateRequirements" placeholder="请输入具体要求（可选）" class="layui-textarea"></textarea>' +
            '</div>' +
            '</div>' +
            '</div>';

        layer.open({
            type: 1,
            title: 'AI代写（队列版）',
            area: ['600px', '400px'],
            content: generateHtml,
            btn: ['开始生成', '取消'],
            yes: function(index) {
                var topic = $('#generateTopic').val();
                var requirements = $('#generateRequirements').val();
                
                if (!topic) {
                    layer.msg('请输入主题');
                    return;
                }
                
                layer.close(index);
                
                var aiLanguage = self.getAiLanguage();
                self.submitTaskToQueue('ai_generate', {
                    topic: topic,
                    requirements: requirements,
                    ai_language: aiLanguage
                }, 'ai_generate', function(result) {
                    var processedResult = self.processMarkdownToHtml(result);
                    var $contentBlock = $(btn).closest('.layui-input-block');
                    var editor = self.getEditor($contentBlock);
                    
                    self.setContent(editor, $contentBlock, processedResult.content);
                    
                    if (processedResult.title) {
                        $('input[name="title"]').val(processedResult.title);
                    }
                    
                    layer.msg('内容生成完成');
                }, function(error) {
                    layer.msg(error);
                });
            }
        });
    }
};

// 页面加载完成后初始化AI助手
$(document).ready(function() {
    // 延迟初始化，确保页面元素完全加载
    setTimeout(function() {
        AiAssistant.init();
    }, 1000);
});