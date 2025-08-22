/**
 * AI助手功能模块
 * 自动检测表单元素并插入对应的AI功能按钮
 */

// AI助手配置
var AiAssistant = {
    // 忽略列表 - 包含这些路径标志的URL将不会初始化AI助手
    ignoreList: [
        '/Config/index',
        '/login',
        '/api/',
        'ajax=1'
    ],
    
    // 初始化AI助手
    init: function() {
        // 检查当前URL是否在忽略列表中
        var currentUrl = window.location.href;
        for (var i = 0; i < this.ignoreList.length; i++) {
            if (currentUrl.indexOf(this.ignoreList[i]) !== -1) {
                return; // 跳过初始化
            }
        }
        
        this.insertTitleOptimizeButton();
        this.insertContentButtons();
        this.insertSubmitOptimizeButton();
    },

    // 在标题输入框后添加标题优化按钮
    insertTitleOptimizeButton: function() {
        var titleInputs = $('input[name="title"]');
        titleInputs.each(function() {
            var $input = $(this);
            if ($input.next('.ai-title-optimize').length === 0) {
                var optimizeBtn = '<div class="ai-title-optimize">' +
                    '<button type="button" class="layui-btn layui-btn-xs layui-btn-primary" onclick="AiAssistant.optimizeTitle(this)">' +
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
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="AiAssistant.contentOptimize(this)">' +
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
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="AiAssistant.contentTranslate(this)">' +
                    '<i class="layui-icon layui-icon-transfer"></i> 内容翻译' +
                    '</button>' +
                    '<button type="button" class="layui-btn layui-btn-sm layui-btn-primary" onclick="AiAssistant.openAiGenerate(this)">' +
                    '<i class="layui-icon layui-icon-add-1"></i> AI代写' +
                    '</button>' +
                    '</div>' +
                    '</div>';
                $parentBlock.append(buttonsHtml);
                
                // 渲染新插入的select组件
                if (typeof layui !== 'undefined') {
                    layui.use('form', function(){
                        var form = layui.form;
                        form.render('select');
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
        console.log($(btn))
        console.log($(btn).closest('.layui-input-block'))
        console.log($(btn).closest('.layui-input-block').find('input[name="title"]'))
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
                url: '/admin.php?p=/Ai/optimizeTitle',
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
            url: '/admin.php?p=/Ai/polish',
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
            url: '/admin.php?p=/Ai/translate',
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
        var generateHtml = '<div style="padding: 20px;">' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">主题：</label>' +
            '<div class="layui-input-block">' +
            '<input type="text" id="generateTopic" placeholder="请输入要生成的内容主题" class="layui-input">' +
            '</div>' +
            '</div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">类型：</label>' +
            '<div class="layui-input-block">' +
            '<select id="generateType">' +
            '<option value="article">文章</option>' +
            '<option value="news">新闻</option>' +
            '<option value="blog">博客</option>' +
            '<option value="product">产品介绍</option>' +
            '<option value="tutorial">教程</option>' +
            '</select>' +
            '</div>' +
            '</div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">长度：</label>' +
            '<div class="layui-input-block">' +
            '<select id="generateLength">' +
            '<option value="short">短文(300字)</option>' +
            '<option value="medium" selected>中等(800字)</option>' +
            '<option value="long">长文(1500字)</option>' +
            '</select>' +
            '</div>' +
            '</div>' +
            '</div>';

        layer.open({
            type: 1,
            title: 'AI代写',
            area: ['500px', '400px'],
            content: generateHtml,
            btn: ['开始生成', '取消'],
            yes: function(index) {
                var topic = $('#generateTopic').val();
                var type = $('#generateType').val();
                var length = $('#generateLength').val();
                
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
                    url: '/admin.php?p=/Ai/generate',
                    type: 'POST',
                    timeout: 60000000,
                    dataType: 'json',
                    data: {
                        topic: topic,
                        type: type,
                        length: length,
                        style: 'professional',
                        language: 'zh',
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
    optimizeBeforeSubmit: function() {
        var self = this;
        
        // 创建优化任务列表
        var tasks = [
            { id: 'title', name: '标题优化', status: 'pending', field: 'input[name="title"]' },
            { id: 'subtitle', name: '副标题生成', status: 'pending', field: 'input[name="subtitle"]' },
            { id: 'url', name: 'URL优化', status: 'pending', field: 'input[name="filename"]' },
            { id: 'keywords', name: 'SEO关键词', status: 'pending', field: 'input[name="keywords"]' },
            { id: 'description', name: 'SEO描述', status: 'pending', field: 'textarea[name="description"]' },
            { id: 'tags', name: '标签生成', status: 'pending', field: 'input[name="tags"]' }
        ];
        
        // 创建进度弹窗HTML
        var progressHtml = '<div class="ai-optimize-progress">' +
            '<div class="progress-header">' +
                '<h3><i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> AI一键优化进行中...</h3>' +
            '</div>' +
            '<div class="progress-content">' +
                '<ul class="task-list">';
        
        tasks.forEach(function(task) {
            progressHtml += '<li class="task-item" data-task="' + task.id + '">' +
                '<i class="task-icon layui-icon layui-icon-time"></i>' +
                '<span class="task-name">' + task.name + '</span>' +
                '<span class="task-status">等待中</span>' +
            '</li>';
        });
        
        progressHtml += '</ul>' +
            '</div>' +
            '<div class="progress-footer">' +
                '<button type="button" class="layui-btn layui-btn-sm" onclick="layer.closeAll()">取消</button>' +
            '</div>' +
        '</div>';
        
        // 添加样式
        var style = '<style>' +
            '.ai-optimize-progress { padding: 20px; min-width: 400px; }' +
            '.progress-header h3 { margin: 0 0 20px 0; color: #333; }' +
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
                // 开始执行优化任务
                self.executeOptimizeTasks(tasks, 0);
            }
        });
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
                self.executeOptimizeTasks(tasks, currentIndex + 1);
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
            url: '/admin.php?p=/Ai/optimizeTitle',
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
            url: '/admin.php?p=/Ai/optimize',
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
    }
};

// 页面加载完成后初始化AI助手
$(document).ready(function() {
    // 延迟初始化，确保页面元素完全加载
    setTimeout(function() {
        AiAssistant.init();
    }, 1000);
});