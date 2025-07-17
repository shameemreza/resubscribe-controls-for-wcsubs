/**
 * Admin JavaScript for Resubscribe Controls for WCSubs
 */
(function ($) {
  "use strict";

  // Initialize on document ready
  $(document).ready(function () {
    // Initialize admin tabs
    initTabs();

    // Initialize rich editor enhancements
    initRichEditor();
  });

  /**
   * Initialize admin tabs
   */
  function initTabs() {
    var $tabs = $(".nav-tab-wrapper a");

    $tabs.on("click", function () {
      var $this = $(this);
      var tab = $this.attr("href").split("tab=")[1];

      // Update active tab
      $tabs.removeClass("nav-tab-active");
      $this.addClass("nav-tab-active");

      // Store active tab in sessionStorage
      if (window.sessionStorage) {
        sessionStorage.setItem("rcwcs_active_tab", tab);
      }
    });
  }

  /**
   * Initialize rich editor enhancements
   */
  function initRichEditor() {
    // Only proceed if we're on the settings page with the email editor
    if (
      $("#rcwcs_price_email_content").length === 0 &&
      $("#rcwcs_price_email_content_ifr").length === 0
    ) {
      return;
    }

    // Add placeholder helper below the editor
    addPlaceholderHelper();

    // Add email preview button
    addEmailPreviewButton();
  }

  /**
   * Add placeholder helper UI
   */
  function addPlaceholderHelper() {
    // Create placeholder help
    var $editorWrap = $("#rcwcs_price_email_content").closest(
      ".wp-editor-wrap"
    );

    if ($editorWrap.length === 0) {
      // Try finding by iframe if the editor is in visual mode
      $editorWrap = $("#rcwcs_price_email_content_ifr").closest(
        ".wp-editor-wrap"
      );

      if ($editorWrap.length === 0) {
        return;
      }
    }

    // Define available placeholders
    var placeholders = [
      { code: "{product_name}", desc: "Subscription product name" },
      { code: "{old_price}", desc: "Original subscription price" },
      { code: "{new_price}", desc: "New subscription price" },
      { code: "{customer_name}", desc: "Customer's full name" },
      { code: "{order_number}", desc: "Resubscription order number" },
      { code: "{site_title}", desc: "Your site name" },
      { code: "{site_url}", desc: "Your site URL" },
    ];

    // Create the placeholder help UI
    var $placeholderHelp = $('<div class="rcwcs-placeholder-help"></div>');
    $placeholderHelp.append("<h4>Available Placeholders</h4>");

    // Create placeholder list
    var $placeholderList = $('<div class="rcwcs-placeholder-list"></div>');

    // Add each placeholder
    $.each(placeholders, function (i, placeholder) {
      var $item = $(
        '<div class="rcwcs-placeholder-item" data-placeholder="' +
          placeholder.code +
          '" title="' +
          placeholder.desc +
          '">' +
          placeholder.code +
          "</div>"
      );
      $placeholderList.append($item);
    });

    $placeholderHelp.append($placeholderList);
    $placeholderHelp.append(
      '<div class="rcwcs-placeholder-tip">Click any placeholder to insert it at the cursor position</div>'
    );

    // Insert after editor
    $editorWrap.after($placeholderHelp);

    // Add click handler for placeholders
    $(".rcwcs-placeholder-item").on("click", function () {
      var placeholder = $(this).data("placeholder");
      insertIntoEditor(placeholder);
    });
  }

  /**
   * Insert content into the TinyMCE editor
   */
  function insertIntoEditor(content) {
    // Insert into TinyMCE if it's active
    if (
      typeof tinymce !== "undefined" &&
      tinymce.get("rcwcs_price_email_content")
    ) {
      tinymce
        .get("rcwcs_price_email_content")
        .execCommand("mceInsertContent", false, content);
      return;
    }

    // Fallback to direct textarea insertion
    var $textarea = $("#rcwcs_price_email_content");

    if ($textarea.length) {
      var startPos = $textarea[0].selectionStart;
      var endPos = $textarea[0].selectionEnd;
      var text = $textarea.val();

      $textarea.val(
        text.substring(0, startPos) + content + text.substring(endPos)
      );

      // Set the cursor position after the inserted content
      $textarea[0].setSelectionRange(
        startPos + content.length,
        startPos + content.length
      );
      $textarea.focus();
    }
  }

  /**
   * Add email preview button
   */
  function addEmailPreviewButton() {
    var $editorWrap = $("#rcwcs_price_email_content").closest(
      ".wp-editor-wrap"
    );

    if ($editorWrap.length === 0) {
      // Try finding by iframe if the editor is in visual mode
      $editorWrap = $("#rcwcs_price_email_content_ifr").closest(
        ".wp-editor-wrap"
      );

      if ($editorWrap.length === 0) {
        return;
      }
    }

    // Create preview button
    var $previewButton = $(
      '<button type="button" class="button button-secondary rcwcs-preview-button">Preview Email Template</button>'
    );

    // Create modal container and overlay
    var $overlay = $('<div id="rcwcs-email-preview-overlay" style="display:none;"></div>');
    var $modalContainer = $('<div id="rcwcs-email-preview-modal" style="display:none;"></div>');
    
    // Add overlay and modal to body
    $("body").append($overlay);
    $("body").append($modalContainer);
    
    // Style the overlay
    $overlay.css({
      position: "fixed",
      top: 0,
      left: 0,
      width: "100%",
      height: "100%",
      background: "rgba(0,0,0,0.5)",
      "z-index": "999998",
    });

    // Insert after placeholder help
    var $placeholderHelp = $editorWrap.next(".rcwcs-placeholder-help");
    if ($placeholderHelp.length) {
      $placeholderHelp.after($previewButton);
    } else {
      $editorWrap.after($previewButton);
    }

    // Preview button click handler
    $previewButton.on("click", function (e) {
      e.preventDefault();

      // Show loading indicator and overlay
      var $loadingIndicator = $(
        '<div id="rcwcs-preview-loading">Loading email preview... Please wait.</div>'
      );
      $loadingIndicator.css({
        padding: "20px",
        "text-align": "center",
        "font-size": "16px",
        color: "#007cba"
      });
      $modalContainer.html($loadingIndicator);
      $overlay.show();
      $modalContainer.show();

      // Get current content
      var content;

      // Get from TinyMCE if it's active
      if (
        typeof tinymce !== "undefined" &&
        tinymce.get("rcwcs_price_email_content")
      ) {
        content = tinymce.get("rcwcs_price_email_content").getContent();
      } else {
        content = $("#rcwcs_price_email_content").val();
      }

      // Get email heading
      var heading = $("#price_email_heading").val();

      // Use AJAX to get a preview
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "rcwcs_preview_email",
          nonce: rcwcs_admin.nonce,
          email_heading: heading,
          email_content: content,
        },
        success: function (response) {
          if (response.success) {
            // Create modal with close button
            var $modal = $('<div class="rcwcs-email-preview-wrapper"></div>');
            var $closeBtn = $(
              '<button type="button" class="button button-secondary rcwcs-preview-close">Close Preview</button>'
            );
            var $iframeContainer = $(
              '<div class="rcwcs-email-preview-iframe"></div>'
            );

            // Create iframe to display HTML email
            var $iframe = $(
              '<iframe id="rcwcs-email-preview-iframe" style="width:100%; height:500px; border:1px solid #ddd;"></iframe>'
            );
            $iframeContainer.append($iframe);

            // Add elements to modal
            $modal.append($closeBtn);
            $modal.append($iframeContainer);
            $modalContainer.html($modal);

            // Write content to iframe
            var iframe = document.getElementById("rcwcs-email-preview-iframe");
            iframe.onload = function () {
              iframe.contentWindow.document.open();
              iframe.contentWindow.document.write(response.data.preview);
              iframe.contentWindow.document.close();
            };

            // Handle close button
            $closeBtn.on("click", function () {
              $overlay.hide();
              $modalContainer.hide();
            });
            
            // Also close when clicking on overlay
            $overlay.on("click", function() {
              $overlay.hide();
              $modalContainer.hide();
            });

            // Style the modal with improved visibility
            $modalContainer.css({
              position: "fixed",
              top: "50px",
              left: "50%",
              transform: "translateX(-50%)",
              width: "80%",
              "max-width": "800px",
              background: "#fff",
              padding: "20px",
              border: "2px solid #007cba",
              "border-radius": "4px",
              "box-shadow": "0 0 20px rgba(0,0,0,0.3)",
              "z-index": "999999",
            });

            $(".rcwcs-email-preview-wrapper").css({
              "max-height": "calc(100vh - 100px)",
              "overflow-y": "auto",
            });

            $(".rcwcs-preview-close").css({
              float: "right",
              "margin-bottom": "10px",
            });
          } else {
            // Show error
            var $errorMsg = $('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
            var $closeBtn = $('<button type="button" class="button">Close</button>').css('margin-top', '10px');
            
            $closeBtn.on('click', function() {
              $overlay.hide();
              $modalContainer.hide();
            });
            
            $modalContainer.html('').append($errorMsg).append($closeBtn);
          }
        },
        error: function () {
          var $errorMsg = $('<div class="notice notice-error"><p>Error loading preview. Please try again.</p></div>');
          var $closeBtn = $('<button type="button" class="button">Close</button>').css('margin-top', '10px');
          
          $closeBtn.on('click', function() {
            $overlay.hide();
            $modalContainer.hide();
          });
          
          $modalContainer.html('').append($errorMsg).append($closeBtn);
        },
      });
    });
  }
})(jQuery);
