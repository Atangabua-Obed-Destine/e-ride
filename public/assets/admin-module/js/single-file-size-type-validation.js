(function () {
  'use strict';

  // Map to store last valid file per input
  const lastValidFiles = new Map();
  const imageFileSizeData = $('.image-file-size-data-to-js') ;
  const maxUploadSizeForImage = imageFileSizeData.data('max-upload-size-for-image');
  const maxUploadSizeForFile = imageFileSizeData.data('max-upload-size-for-file');
  const postMaxSize = imageFileSizeData.data('post-max-size');

  function parseAccept(accept) {
    if (!accept) return [];
    return accept.split(',')
      .map(s => s.trim().toLowerCase())
      .filter(Boolean);
  }

  function fileMatchesAccept(file, accepted) {
    if (!accepted || !accepted.length) return true;
    const fileType = (file.type || '').toLowerCase();
    const name = file.name || '';
    const ext = '.' + name.split('.').pop().toLowerCase();
    for (const a of accepted) {
      if (a.startsWith('.') && ext === a) return true;
      if (a.includes('/') && a.endsWith('/*') && fileType.startsWith(a.split('/')[0] + '/')) return true;
      if (a === fileType) return true;
      if (a === ext) return true;
    }
    return false;
  }

  function getMaxBytes(input) {
    const m = $(input).attr('data-max-upload-size');
    const file = input.files && input.files[0];
    let defaultFileSize = maxUploadSizeForImage;
    if (file) {
        const fileName = file.name.toLowerCase();
        const ext = fileName.split('.').pop();

        const fileExtensions = ['txt', 'rtf', 'doc', 'docx', 'pdf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'log'];
        if (fileExtensions.includes(ext)) {
            defaultFileSize = maxUploadSizeForFile;
        }
    }
    const mb = (m && !isNaN(parseFloat(m))) ? parseFloat(m) : parseFloat(defaultFileSize);
    return mb * 1024 * 1024;
  }

  function showError(msg) {
    if (typeof toastr !== 'undefined' && toastr.error) {
      toastr.error(msg);
    } else {
      console.error(msg);
      alert(msg);
    }
  }

  function restorePreview(input, file) {
    const previewImg = document.querySelector(`[data-preview-for="${input.id}"]`);
    if (!previewImg) return;
    if (file) {
      previewImg.src = URL.createObjectURL(file);
    } else {
      const placeholder = input.getAttribute('data-placeholder');
      previewImg.src = placeholder || '';
    }
  }

  function validatingChangeHandler(ev) {
    if (!ev || !ev.target || ev.target.tagName !== 'INPUT' || ev.target.type !== 'file' || ev.target.classList.contains('spartan_image_input')) return;

    const input = ev.target;
    const files = Array.from(input.files || []);
    if (!files.length) return;

    const accepted = parseAccept(input.getAttribute('accept') || '');
    const maxBytes = getMaxBytes(input);
    const maxPostBytes = parseFloat(postMaxSize) * 1024 * 1024;

    let validFile = null;
    let anyInvalid = false;

    for (const file of files) {
      const name = file.name || 'file';
      if (!fileMatchesAccept(file, accepted)) {
        showError(`"${name}" is not an allowed file type. Only "${input.getAttribute('accept')}" file types are accepted`);
        anyInvalid = true;
        break;
      }
      if (file.size > maxBytes) {
        const mb = Math.round((maxBytes / (1024 * 1024)) * 100) / 100;
        showError(`"${name}" exceeds the maximum file size limit of ${mb}MB.`);
        anyInvalid = true;
        break;
      }

      validFile = file; // last valid
    }

    if (!anyInvalid && typeof canAddUploadFiles === 'function' && !canAddUploadFiles(files, {form: input.form, excludeInput: input})) {
      anyInvalid = true;
    } else if (!anyInvalid && files.some(file => file.size > maxPostBytes)) {
      const mb = Math.round((maxPostBytes / (1024 * 1024)) * 100) / 100;
      showError(`Maximum ${mb}MB can be uploaded.`);
      anyInvalid = true;
    }

    if (anyInvalid) {
      // restore last valid file if exists
      const lastFile = lastValidFiles.get(input);
      if (lastFile) {
        const dt = new DataTransfer();
        dt.items.add(lastFile);
        input.files = dt.files;
      } else {
        input.value = '';
      }
      restorePreview(input, lastValidFiles.get(input));
      ev.stopImmediatePropagation();
      ev.preventDefault();
      ev.stopPropagation();
      return false;
    }

    // Save last valid file
    if (validFile) {
      lastValidFiles.set(input, validFile);
      restorePreview(input, validFile);
    }

    return true;
  }

    document.addEventListener('change', validatingChangeHandler, true);

})();
