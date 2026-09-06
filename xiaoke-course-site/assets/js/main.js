/* 小课学堂 - 前端交互脚本 */

// 支付方式选择
document.querySelectorAll('.pay-method').forEach(function (el) {
  el.addEventListener('click', function () {
    document.querySelectorAll('.pay-method').forEach(function (m) { m.classList.remove('selected'); });
    el.classList.add('selected');
    var input = document.querySelector('input[name="pay_method"]');
    if (input) input.value = el.dataset.method || '';
  });
});

// 倒计时按钮（发送验证码）
window.startCodeCooldown = function (btn, seconds) {
  var left = seconds || 60;
  btn.disabled = true;
  var origin = btn.textContent;
  var timer = setInterval(function () {
    left--;
    btn.textContent = left + 's 后重发';
    if (left <= 0) {
      clearInterval(timer);
      btn.disabled = false;
      btn.textContent = origin;
    }
  }, 1000);
};
