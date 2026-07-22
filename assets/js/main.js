/**
 * MIUIROM - 前端交互脚本
 * 提供MD5校验值展示、表格排序、复制链接等功能
 */
document.addEventListener('DOMContentLoaded', function() {
    // MD5校验值展示
    initMd5Buttons();
    
    // 复制下载链接
    initCopyButtons();
    
    // 表格行点击
    initTableClicks();
});

/**
 * MD5按钮 - 点击显示校验值
 */
function initMd5Buttons() {
    document.querySelectorAll('.btn-md5').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var md5 = this.getAttribute('title').replace('MD5: ', '');
            var temp = document.createElement('textarea');
            temp.value = md5;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            
            var originalText = this.textContent;
            this.textContent = '已复制';
            this.style.background = '#52c41a';
            this.style.color = '#fff';
            
            setTimeout(function(btn, originalText) {
                btn.textContent = originalText;
                btn.style.background = '';
                btn.style.color = '';
            }, 1500, this, originalText);
        });
    });
}

/**
 * 复制链接按钮(如存在)
 */
function initCopyButtons() {
    document.querySelectorAll('.btn-copy-link').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var url = this.getAttribute('data-url');
            if (!url) return;
            
            var temp = document.createElement('textarea');
            temp.value = url;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            
            var original = this.textContent;
            this.textContent = '已复制';
            setTimeout(function(btn, original) {
                btn.textContent = original;
            }, 1500, this, original);
        });
    });
}

/**
 * 表格行点击跳转
 */
function initTableClicks() {
    document.querySelectorAll('.rom-table tbody tr').forEach(function(row) {
        row.addEventListener('click', function(e) {
            // 不拦截按钮点击
            if (e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            var link = row.querySelector('a.device-link');
            if (link) {
                window.location = link.href;
            }
        });
        row.style.cursor = 'pointer';
    });
}