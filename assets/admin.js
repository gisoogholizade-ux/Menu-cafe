(() => {
  const imageInput = document.getElementById('imageInput');
  const preview = document.getElementById('imagePreview');
  if (imageInput && preview) {
    imageInput.addEventListener('change', () => {
      const file = imageInput.files && imageInput.files[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      const img = document.createElement('img');
      img.src = url;
      img.alt = 'پیش‌نمایش تصویر';
      img.onload = () => URL.revokeObjectURL(url);
      preview.replaceChildren(img);
    });
  }

  const urlInput = document.getElementById('menuUrl');
  const qrBox = document.getElementById('qrcode');
  if (urlInput && qrBox && window.QRCode) {
    new QRCode(qrBox, {
      text: urlInput.value,
      width: 190,
      height: 190,
      colorDark: '#080808',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.H
    });
  }

  const copyButton = document.getElementById('copyUrl');
  if (copyButton && urlInput) {
    copyButton.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(urlInput.value);
        const old = copyButton.textContent;
        copyButton.textContent = 'کپی شد ✓';
        setTimeout(() => { copyButton.textContent = old; }, 1400);
      } catch (_) {
        urlInput.select();
        document.execCommand('copy');
      }
    });
  }

  const downloadButton = document.getElementById('downloadQr');
  if (downloadButton && qrBox) {
    downloadButton.addEventListener('click', () => {
      const canvas = qrBox.querySelector('canvas');
      const img = qrBox.querySelector('img');
      const href = canvas ? canvas.toDataURL('image/png') : (img ? img.src : '');
      if (!href) return;
      const link = document.createElement('a');
      link.href = href;
      link.download = 'cafe-menu-qr.png';
      document.body.appendChild(link);
      link.click();
      link.remove();
    });
  }
})();
