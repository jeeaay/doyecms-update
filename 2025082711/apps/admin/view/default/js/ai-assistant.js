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
            'portuguese': '葡萄牙语',
            'chinese': '中文'
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
                    '<i class="layui-icon layui-icon-edit"></i> AI优化' +
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
                    '<i class="layui-icon layui-icon-edit"></i> 内容优化' +
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
                    '<i class="layui-icon layui-icon-transfer"></i> 内容翻译' +
                    '</button>' +
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="AiAssistant.openAiGenerateQueue(this)">' +
                    '<i class="layui-icon layui-icon-add-1"></i> AI代写' +
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
                    '<input type="radio" name="articleLang" value="english" title="英语" checked lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="spanish" title="西班牙语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="russian" title="俄语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="french" title="法语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="arabic" title="阿拉伯语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="portuguese" title="葡萄牙语" lay-filter="articleLang">' +
                    '<input type="radio" name="articleLang" value="chinese" title="中文" lay-filter="articleLang">' +
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









    // 提交前AI优化
    /**
     * AI一键优化（单次请求版本）
     * 发送一次请求获取所有SEO相关数据
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
        
        // 检查页面中存在的字段
        var availableFields = {
            subtitle: $('input[name="subtitle"]').length > 0,
            description: $('textarea[name="description"]').length > 0,
            keywords: $('input[name="keywords"]').length > 0,
            tags: $('input[name="tags"]').length > 0,
            url: $('input[name="filename"]').length > 0
        };
        
        // 检查是否有可优化的字段
        var hasAvailableFields = Object.values(availableFields).some(function(exists) { return exists; });
        if (!hasAvailableFields) {
            layer.msg('未找到可优化的字段', {icon: 2});
            return;
        }
        
        // 直接开始单次优化请求，显示简单的加载提示
        var loadingIndex = layer.msg('AI一键优化进行中...', {
            icon: 16,
            shade: 0.3,
            time: 0
        });
        
        // 开始单次优化请求
        self.executeSingleOptimizationRequest(title, content, availableFields, loadingIndex);
    },
    
    /**
     * 执行单次优化请求
     * @param {string} title - 文章标题
     * @param {string} content - 文章内容
     * @param {Object} availableFields - 可用字段映射
     * @param {number} loadingIndex - 加载提示索引
     */
    executeSingleOptimizationRequest: function(title, content, availableFields, loadingIndex) {
        var self = this;
        
        // 获取AI语言设置
        var aiLanguage = this.getAiLanguage();
        // 构建请求数据
        var requestData = {
            language: aiLanguage,
            title: title || '',
            content: content || '',
            tasks: [
                {type: 'subtitle'},
                {type: 'description'},
                {type: 'keywords'},
                {type: 'tags'},
                {type: 'url'}
            ] // 添加任务列表，格式与后端期望一致
        };
        
        // 提交到队列处理
        this.submitTaskToQueue('seo_optimize', requestData, 'seo_optimize',
            function(result) {
                // 请求成功，解析JSON结果
                try {
                    var seoData;
                    if (typeof result === 'string') {
                        seoData = JSON.parse(result);
                    } else {
                        seoData = result;
                    }
                    
                    // 更新表单字段
                    self.updateSeoFields(seoData, availableFields);
                    
                    // 关闭加载提示并显示成功消息
                    layer.close(loadingIndex);
                    layer.msg('AI一键优化完成！', {icon: 1});
                    
                } catch (e) {
                    console.error('解析SEO数据失败:', e, result);
                    self.handleOptimizationError('数据解析失败', loadingIndex);
                }
            },
            function(error) {
                // 请求失败
                console.error('SEO优化请求失败:', error);
                self.handleOptimizationError(error, loadingIndex);
            }
        );
    },
    
    /**
     * 更新SEO相关字段
     * @param {Object} seoData - SEO数据
     * @param {Object} availableFields - 可用字段映射
     */
    updateSeoFields: function(seoData, availableFields) {
        // 处理后端返回的数据结构：{subtitle: {success: true, data: "值"}, ...}
        if (availableFields.subtitle && seoData.subtitle && seoData.subtitle.success) {
            $('input[name="subtitle"]').val(seoData.subtitle.data);
        }
        if (availableFields.description && seoData.description && seoData.description.success) {
            $('textarea[name="description"]').val(seoData.description.data);
        }
        if (availableFields.keywords && seoData.keywords && seoData.keywords.success) {
            $('input[name="keywords"]').val(seoData.keywords.data);
        }
        if (availableFields.tags && seoData.tags && seoData.tags.success) {
            $('input[name="tags"]').val(seoData.tags.data);
        }
        if (availableFields.url && seoData.url && seoData.url.success) {
            $('input[name="filename"]').val(seoData.url.data);
        }
        
        // 处理失败的字段，显示错误信息
        var failedFields = [];
        if (availableFields.subtitle && seoData.subtitle && !seoData.subtitle.success) {
            failedFields.push('副标题: ' + (seoData.subtitle.error || '未知错误'));
        }
        if (availableFields.description && seoData.description && !seoData.description.success) {
            failedFields.push('描述: ' + (seoData.description.error || '未知错误'));
        }
        if (availableFields.keywords && seoData.keywords && !seoData.keywords.success) {
            failedFields.push('关键词: ' + (seoData.keywords.error || '未知错误'));
        }
        if (availableFields.tags && seoData.tags && !seoData.tags.success) {
            failedFields.push('标签: ' + (seoData.tags.error || '未知错误'));
        }
        if (availableFields.url && seoData.url && !seoData.url.success) {
            failedFields.push('URL: ' + (seoData.url.error || '未知错误'));
        }
        
        // 如果有失败的字段，显示警告信息
        if (failedFields.length > 0) {
            console.warn('部分字段优化失败:', failedFields);
            layer.msg('部分字段优化失败，请查看控制台了解详情', {icon: 0});
        }
    },
    
    /**
     * 处理优化错误
     * @param {string} error - 错误信息
     * @param {number} loadingIndex - 加载提示索引
     */
    handleOptimizationError: function(error, loadingIndex) {
        // 关闭加载提示并显示错误信息
        layer.close(loadingIndex);
        layer.msg('AI优化失败: ' + error, {icon: 2});
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
     * 获取当前页面的p参数
     * @returns {string} 当前页面的p参数值
     */
    getCurrentPageParam: function() {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('p') || '';
    },
    
    /**
     * 检查页面加载时是否有未完成的任务
     */
    checkExistingTask: function() {
        var taskId = localStorage.getItem('ai_task_id');
        var taskType = localStorage.getItem('ai_task_type');
        var storedPageParam = localStorage.getItem('ai_task_page_param');
        var currentPageParam = this.getCurrentPageParam();
        
        // 只有在相同页面（p参数相同）才恢复任务
        if (taskId && taskType && storedPageParam === currentPageParam) {
            this.currentTaskId = taskId;
            
            // 根据任务类型重新创建成功回调函数
            var successCallback = this.createCallbackByType(taskType);
            this.checkTaskStatus(taskId, true, successCallback);
        } else if (taskId && storedPageParam !== currentPageParam) {
            // 如果页面不同，清除旧任务
            this.clearTaskStorage();
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
            url: location.pathname + '?p=/Ai/aiQueueSubmit',
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
                    localStorage.setItem('ai_task_page_param', self.getCurrentPageParam());
                    
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
            url: location.pathname + '?p=/Ai/aiQueueStatus',
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
        localStorage.removeItem('ai_task_page_param');
        
        this.stopPolling();
    },
    
    /**
     * 清除任务存储（别名函数，保持兼容性）
     */
    clearTaskStorage: function() {
        this.clearTask();
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
                url: location.pathname + '?p=/Ai/aiQueueCancel',
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
            title: 'AI代写',
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