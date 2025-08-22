/**
 * 图片剪裁功能集成模块
 * 基于Cropper.js实现图片剪裁上传功能
 */

(function() {
    'use strict';
    
    // 全局配置
    var CropperConfig = {
        // 支持的文件类型
        allowedTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
        // 最大文件大小 (5MB)
        maxFileSize: 5 * 1024 * 1024,
        // 预设剪裁比例
        aspectRatios: {
            'free': { value: NaN, label: '自由剪裁' },
            '1:1': { value: 1, label: '正方形 1:1' },
            '4:3': { value: 4/3, label: '横向 4:3' },
            '3:4': { value: 3/4, label: '竖向 3:4' },
            '16:9': { value: 16/9, label: '宽屏 16:9' },
            '9:16': { value: 9/16, label: '竖屏 9:16' }
        },
        // 预设图片尺寸
        imageSizes: {
            'avatar': { width: 200, height: 200, label: '头像 200×200' },
            'thumbnail': { width: 300, height: 200, label: '缩略图 300×200' },
            'banner': { width: 1200, height: 400, label: '横幅 1200×400' },
            'post': { width: 800, height: 600, label: '文章配图 800×600' },
            'card': { width: 400, height: 300, label: '卡片图 400×300' },
            'icon': { width: 64, height: 64, label: '图标 64×64' },
            'logo': { width: 300, height: 100, label: 'Logo 300×100' },
            'cover': { width: 1920, height: 1080, label: '封面图 1920×1080' }
        },
        // Cropper.js配置
        cropperOptions: {
            aspectRatio: NaN,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.8,
            restore: false,
            guides: true,
            center: true,
            highlight: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            responsive: true,
            checkOrientation: false
        }
    };
    
    /**
     * 图片剪裁器类
     */
    function ImageCropper() {
        this.cropper = null;
        this.currentFile = null;
        this.currentElement = null;
        this.uploadCallback = null;
        this.init();
    }
    
    ImageCropper.prototype = {
        /**
         * 初始化剪裁器
         */
        init: function() {
            this.createModal();
            this.bindEvents();
        },
        
        /**
         * 创建剪裁弹窗HTML结构
         */
        createModal: function() {
            var modalHtml = [
                '<div id="cropModal" class="crop-modal">',
                '  <div class="crop-modal-content">',
                '    <div class="crop-modal-header">',
                '      <h3 class="crop-modal-title">图片剪裁</h3>',
                '      <span class="crop-modal-close">&times;</span>',
                '    </div>',
                '    <div class="crop-modal-body">',
                '      <div class="crop-drop-zone" id="cropDropZone">',
                '        <i class="layui-icon layui-icon-upload"></i>',
                '        <p>点击选择图片或拖拽图片到此处</p>',
                '        <p style="font-size: 12px; color: #999;">支持 JPG、PNG、GIF、WebP 格式，最大 5MB</p>',
                '      </div>',
                '      <input type="file" id="cropFileInput" class="crop-file-input" accept="image/*">',
                '      <div class="crop-container" id="cropContainer" style="display: none;">',
                '        <img id="cropImage" src="" alt="待剪裁图片">',
                '      </div>',
                '      <div class="crop-ratio-selector" id="cropRatioSelector" style="display: none;">',
                '        <label class="layui-form-label">剪裁比例:</label>',
                '        <div class="crop-ratio-buttons" id="cropRatioButtons"></div>',
                '      </div>',
                '      <div class="crop-size-selector" id="cropSizeSelector" style="display: none;">',
                '        <label class="layui-form-label">预设尺寸:</label>',
                '        <div class="crop-size-buttons" id="cropSizeButtons"></div>',
                '      </div>',
                '      <div class="crop-preview-section" id="cropPreviewSection" style="display: none;">',
                '        <label class="layui-form-label">预览效果:</label>',
                '        <div class="crop-preview-container">',
                '          <div class="crop-preview-box crop-preview-small" id="cropPreviewSmall"></div>',
                '          <div class="crop-preview-box crop-preview-medium" id="cropPreviewMedium"></div>',
                '          <div class="crop-preview-info">',
                '            <div class="crop-info-item">',
                '              <span class="crop-info-label">原始尺寸:</span>',
                '              <span id="cropOriginalSize">-</span>',
                '            </div>',
                '            <div class="crop-info-item">',
                '              <span class="crop-info-label">剪裁尺寸:</span>',
                '              <span id="cropResultSize">-</span>',
                '            </div>',
                '            <div class="crop-info-item">',
                '              <span class="crop-info-label">文件大小:</span>',
                '              <span id="cropFileSize">-</span>',
                '            </div>',
                '          </div>',
                '        </div>',
                '      </div>',
                '      <div class="crop-error" id="cropError"></div>',
                '      <div class="crop-success" id="cropSuccess"></div>',
                '    </div>',
                '    <div class="crop-modal-footer">',
                '      <button type="button" class="layui-btn layui-btn-primary" id="cropCancelBtn">取消</button>',
                '      <button type="button" class="layui-btn layui-btn-normal" id="cropResetBtn" style="display: none;">重置</button>',
                '      <button type="button" class="layui-btn upload-crop-btn" id="cropConfirmBtn" style="display: none;">确认上传</button>',
                '    </div>',
                '    <div class="crop-loading" id="cropLoading" style="display: none;">',
                '      <div class="crop-loading-spinner"></div>',
                '      <div>正在上传...</div>',
                '    </div>',
                '  </div>',
                '</div>'
            ].join('');
            
            // 添加到页面
            $('body').append(modalHtml);
            
            // 创建比例按钮
            this.createRatioButtons();
        },
        
        /**
         * 创建剪裁比例按钮和尺寸按钮
         */
        createRatioButtons: function() {
            // 创建比例按钮
            var ratioButtonsHtml = '';
            for (var key in CropperConfig.aspectRatios) {
                var ratio = CropperConfig.aspectRatios[key];
                ratioButtonsHtml += '<button type="button" class="crop-ratio-btn" data-ratio="' + key + '">' + ratio.label + '</button>';
            }
            $('#cropRatioButtons').html(ratioButtonsHtml);
            
            // 创建尺寸按钮
            var sizeButtonsHtml = '';
            for (var key in CropperConfig.imageSizes) {
                var size = CropperConfig.imageSizes[key];
                sizeButtonsHtml += '<button type="button" class="crop-size-btn" data-size="' + key + '">' + size.label + '</button>';
            }
            $('#cropSizeButtons').html(sizeButtonsHtml);
        },
        
        /**
         * 绑定事件
         */
        bindEvents: function() {
            var self = this;
            
            // 关闭弹窗
            $(document).on('click', '.crop-modal-close, #cropCancelBtn', function() {
                self.closeModal();
            });
            
            // 点击背景关闭
            $(document).on('click', '.crop-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // 文件选择
            $(document).on('change', '#cropFileInput', function(e) {
                self.handleFileSelect(e.target.files[0]);
            });
            
            // 拖拽区域点击
            $(document).on('click', '#cropDropZone', function() {
                $('#cropFileInput').click();
            });
            
            // 拖拽上传
            $(document).on('dragover', '#cropDropZone', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });
            
            $(document).on('dragleave', '#cropDropZone', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });
            
            $(document).on('drop', '#cropDropZone', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleFileSelect(files[0]);
                }
            });
            
            // 比例按钮点击
            $(document).on('click', '.crop-ratio-btn', function() {
                var ratio = $(this).data('ratio');
                self.setAspectRatio(ratio);
                $('.crop-ratio-btn').removeClass('active');
                $(this).addClass('active');
                // 清除尺寸按钮选中状态
                $('.crop-size-btn').removeClass('active');
            });
            
            // 尺寸按钮点击
            $(document).on('click', '.crop-size-btn', function() {
                var sizeKey = $(this).data('size');
                self.setSizePreset(sizeKey);
                $('.crop-size-btn').removeClass('active');
                $(this).addClass('active');
                // 清除比例按钮选中状态
                $('.crop-ratio-btn').removeClass('active');
            });
            
            // 重置按钮
            $(document).on('click', '#cropResetBtn', function() {
                self.resetCropper();
            });
            
            // 确认上传按钮
            $(document).on('click', '#cropConfirmBtn', function() {
                self.confirmCrop();
            });
        },
        
        /**
         * 显示剪裁弹窗
         */
        showModal: function(element, callback) {
            this.currentElement = element;
            this.uploadCallback = callback;
            $('#cropModal').addClass('show');
            this.resetModal();
        },
        
        /**
         * 关闭剪裁弹窗
         */
        closeModal: function() {
            $('#cropModal').removeClass('show');
            this.destroyCropper();
            this.resetModal();
        },
        
        /**
         * 重置弹窗状态
         */
        resetModal: function() {
            $('#cropDropZone').show();
            $('#cropContainer').hide();
            $('#cropRatioSelector').hide();
            $('#cropPreviewSection').hide();
            $('#cropResetBtn').hide();
            $('#cropConfirmBtn').hide();
            $('#cropError').removeClass('show');
            $('#cropSuccess').removeClass('show');
            $('.crop-ratio-btn').removeClass('active');
            $('.crop-ratio-btn[data-ratio="free"]').addClass('active');
        },
        
        /**
         * 处理文件选择
         */
        handleFileSelect: function(file) {
            if (!file) return;
            
            // 验证文件类型
            if (CropperConfig.allowedTypes.indexOf(file.type) === -1) {
                this.showError('不支持的文件格式，请选择 JPG、PNG、GIF 或 WebP 格式的图片');
                return;
            }
            
            // 验证文件大小
            if (file.size > CropperConfig.maxFileSize) {
                this.showError('文件大小超过限制，请选择小于 5MB 的图片');
                return;
            }
            
            this.currentFile = file;
            this.loadImage(file);
        },
        
        /**
         * 加载图片
         */
        loadImage: function(file) {
            var self = this;
            var reader = new FileReader();
            
            reader.onload = function(e) {
                $('#cropImage').attr('src', e.target.result);
                $('#cropDropZone').hide();
                $('#cropContainer').show();
                $('#cropRatioSelector').show();
                $('#cropSizeSelector').show();
                $('#cropPreviewSection').show();
                $('#cropResetBtn').show();
                $('#cropConfirmBtn').show();
                
                // 初始化Cropper
                self.initCropper();
                
                // 更新文件信息
                self.updateFileInfo();
            };
            
            reader.readAsDataURL(file);
        },
        
        /**
         * 初始化Cropper.js
         */
        initCropper: function() {
            var self = this;
            var image = document.getElementById('cropImage');
            
            this.destroyCropper();
            
            var options = $.extend({}, CropperConfig.cropperOptions, {
                ready: function() {
                    self.updatePreview();
                },
                cropend: function() {
                    self.updatePreview();
                }
            });
            
            this.cropper = new Cropper(image, options);
        },
        
        /**
         * 销毁Cropper实例
         */
        destroyCropper: function() {
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
        },
        
        /**
         * 设置剪裁比例
         */
        setAspectRatio: function(ratioKey) {
            if (!this.cropper) return;
            
            var ratio = CropperConfig.aspectRatios[ratioKey];
            if (ratio) {
                this.cropper.setAspectRatio(ratio.value);
            }
        },
        
        /**
         * 设置尺寸预设
         */
        setSizePreset: function(sizeKey) {
            if (!this.cropper) return;
            
            var size = CropperConfig.imageSizes[sizeKey];
            if (size) {
                // 计算比例
                var aspectRatio = size.width / size.height;
                this.cropper.setAspectRatio(aspectRatio);
                
                // 获取当前图片的尺寸
                var imageData = this.cropper.getImageData();
                var containerData = this.cropper.getContainerData();
                
                // 计算合适的剪裁框尺寸
                var cropBoxWidth = Math.min(size.width, containerData.width * 0.8);
                var cropBoxHeight = cropBoxWidth / aspectRatio;
                
                // 如果高度超出容器，重新计算
                if (cropBoxHeight > containerData.height * 0.8) {
                    cropBoxHeight = containerData.height * 0.8;
                    cropBoxWidth = cropBoxHeight * aspectRatio;
                }
                
                // 设置剪裁框尺寸和位置
                this.cropper.setCropBoxData({
                    width: cropBoxWidth,
                    height: cropBoxHeight,
                    left: (containerData.width - cropBoxWidth) / 2,
                    top: (containerData.height - cropBoxHeight) / 2
                });
            }
        },
        
        /**
         * 重置剪裁器
         */
        resetCropper: function() {
            if (this.cropper) {
                this.cropper.reset();
                this.updatePreview();
            }
        },
        
        /**
         * 更新预览
         */
        updatePreview: function() {
            if (!this.cropper) return;
            
            var canvas = this.cropper.getCroppedCanvas({
                width: 100,
                height: 100
            });
            
            var canvas2 = this.cropper.getCroppedCanvas({
                width: 150,
                height: 150
            });
            
            if (canvas && canvas2) {
                $('#cropPreviewSmall').html(canvas);
                $('#cropPreviewMedium').html(canvas2);
                
                // 更新剪裁尺寸信息
                var cropBoxData = this.cropper.getCropBoxData();
                $('#cropResultSize').text(Math.round(cropBoxData.width) + ' × ' + Math.round(cropBoxData.height));
            }
        },
        
        /**
         * 更新文件信息
         */
        updateFileInfo: function() {
            if (!this.currentFile) return;
            
            var img = new Image();
            var self = this;
            
            img.onload = function() {
                $('#cropOriginalSize').text(this.width + ' × ' + this.height);
            };
            
            img.src = URL.createObjectURL(this.currentFile);
            
            // 文件大小
            var size = self.currentFile.size;
            var sizeText = size < 1024 ? size + ' B' :
                          size < 1024 * 1024 ? (size / 1024).toFixed(1) + ' KB' :
                          (size / (1024 * 1024)).toFixed(1) + ' MB';
            $('#cropFileSize').text(sizeText);
        },
        
        /**
         * 确认剪裁并上传
         */
        confirmCrop: function() {
            if (!this.cropper) return;
            
            var self = this;
            this.showLoading(true);
            
            // 获取剪裁后的canvas
            var canvas = this.cropper.getCroppedCanvas({
                maxWidth: 2000,
                maxHeight: 2000,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });
            
            if (!canvas) {
                this.showError('剪裁失败，请重试');
                this.showLoading(false);
                return;
            }
            
            // 转换为Blob
            canvas.toBlob(function(blob) {
                if (!blob) {
                    self.showError('图片处理失败，请重试');
                    self.showLoading(false);
                    return;
                }
                
                // 创建FormData
                var formData = new FormData();
                var fileName = self.currentFile.name.replace(/\.[^/.]+$/, '') + '_cropped.jpg';
                formData.append('file', blob, fileName);
                
                // 调用上传回调
                if (self.uploadCallback) {
                    self.uploadCallback(formData, function(success, data) {
                        self.showLoading(false);
                        if (success) {
                            self.showSuccess('上传成功');
                            setTimeout(function() {
                                self.closeModal();
                            }, 1000);
                        } else {
                            self.showError(data || '上传失败，请重试');
                        }
                    });
                } else {
                    self.showLoading(false);
                    self.showError('上传功能未配置');
                }
            }, 'image/jpeg', 0.9);
        },
        
        /**
         * 显示加载状态
         */
        showLoading: function(show) {
            if (show) {
                $('#cropLoading').show();
            } else {
                $('#cropLoading').hide();
            }
        },
        
        /**
         * 显示错误信息
         */
        showError: function(message) {
            $('#cropError').text(message).addClass('show');
            $('#cropSuccess').removeClass('show');
            setTimeout(function() {
                $('#cropError').removeClass('show');
            }, 5000);
        },
        
        /**
         * 显示成功信息
         */
        showSuccess: function(message) {
            $('#cropSuccess').text(message).addClass('show');
            $('#cropError').removeClass('show');
        }
    };
    
    // 全局实例
    window.ImageCropper = new ImageCropper();
    
    /**
     * 为上传按钮添加剪裁功能
     */
    window.addCropUploadButton = function(selector) {
        console.log('addCropUploadButton 被调用，选择器:', selector);
        
        $(selector).each(function(index) {
            var $uploadBtn = $(this);
            console.log('处理第', index + 1, '个上传按钮:', $uploadBtn[0]);
            
            // 检查是否已经添加过剪裁按钮
            if ($uploadBtn.next('.upload-crop-btn').length > 0) {
                console.log('按钮已存在，跳过');
                return;
            }
            
            var $cropBtn = $('<button type="button" class="layui-btn upload-crop-btn">上传并剪裁</button>');
            
            // 插入到上传按钮后面
            $uploadBtn.after($cropBtn);
            console.log('剪裁按钮已插入');
            
            // 绑定点击事件
            $cropBtn.on('click', function(e) {
                e.preventDefault();
                console.log('剪裁按钮被点击');
                
                var element = $uploadBtn[0];
                
                // 获取上传配置
                var isWatermark = $uploadBtn.hasClass('watermark');
                var uploadUrl = isWatermark ? 
                    '/admin.php?c=upload&f=uploadpic&watermark=1' : 
                    '/admin.php?c=upload&f=uploadpic';
                
                // 显示剪裁弹窗
                window.ImageCropper.showModal(element, function(formData, callback) {
                    // 执行上传
                    $.ajax({
                        url: uploadUrl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.code === 1) {
                                // 更新预览图片
                                var $preview = $uploadBtn.siblings('.layui-upload-img');
                                if ($preview.length) {
                                    $preview.attr('src', response.data.url).show();
                                }
                                
                                // 更新隐藏域
                                var $input = $uploadBtn.siblings('input[type="hidden"]');
                                if ($input.length) {
                                    $input.val(response.data.url);
                                }
                                
                                callback(true, response.data);
                            } else {
                                callback(false, response.msg || '上传失败');
                            }
                        },
                        error: function() {
                            callback(false, '网络错误，请重试');
                        }
                    });
                });
            });
        });
    };
    
    // 初始化剪裁功能
    function initCropperFeature() {
        console.log('cropper-integration.js 开始初始化');
        
        // 检查jQuery是否已加载
        if (typeof $ === 'undefined') {
            console.error('jQuery 未加载，剪裁功能不可用');
            return;
        }
        
        // 检查Cropper.js是否已加载
        if (typeof Cropper === 'undefined') {
            console.warn('Cropper.js 未加载，剪裁功能不可用');
            return;
        }
        
        console.log('依赖库检查完成，开始添加剪裁按钮');
        
        // 为现有的上传按钮添加剪裁功能
        var uploadButtons = $('.upload, .uploads');
        console.log('找到上传按钮数量:', uploadButtons.length);
        
        if (uploadButtons.length > 0) {
            window.addCropUploadButton('.upload, .uploads');
            console.log('剪裁按钮添加完成');
        } else {
            console.warn('未找到上传按钮，可能页面还未完全加载');
        }
    }
    
    // 页面加载完成后延迟初始化，确保layui已经完成初始化
    $(document).ready(function() {
        console.log('DOM加载完成，准备初始化剪裁功能');
        
        // 等待layui初始化完成
        setTimeout(function() {
            console.log('开始第一次初始化尝试');
            initCropperFeature();
        }, 1000);
        
        // 如果第一次没找到按钮，再次尝试
        setTimeout(function() {
            if ($('.upload-crop-btn').length === 0) {
                console.log('第一次初始化未找到按钮，重新尝试');
                initCropperFeature();
            }
        }, 3000);
    });
    
    // 也可以手动调用初始化
    window.initCropperFeature = initCropperFeature;
    
})();