var ajaxResponse = null;

document.addEventListener("DOMContentLoaded", function () {
  const mediaContainer = jQuery(".media");
  const memberPageContainer = jQuery(".member-media");
  const videoContainer = jQuery("#video-stream");
 const mainContainerr = jQuery('#buddypress');
  const activityContainer = jQuery("#activity-stream");
  var newDiv = jQuery(`<div class="deaddove-media-modal-wrapper">
            <div class="deaddove-modal" style="display:none;">
                <div class="deaddove-modal-content">
                    <p class="description-text">Data not good</p>
                    <div class="modal-buttons">
                        <button class="deaddove-show-content-btn">Show this content</button>
                        <button class="deaddove-hide-content-btn">Keep it hidden</button>
                    </div>
                    <small><a href="#deaddove-warning-settings" class="deaddove-settings-link">Modify your content warning settings</a></small>
                </div>
            </div> `);
  mediaContainer.append(newDiv);
  memberPageContainer.append(newDiv);
  videoContainer.append(newDiv);
  activityContainer.append(newDiv);
  mainContainerr.append(newDiv);
  const targetNode = document.getElementById("media-stream");
   
  function getContentWarningData() {
    return new Promise(function (resolve, reject) {
      var data = {
        action: "deaddove_content_warning",
        nonce: deaddove_ajax.nonce,
      };

      jQuery
        .post(deaddove_ajax.ajaxurl, data, function (response) {
          if (response.success) {
            resolve(response.data.activities);
          } else {
            reject("Error: " + response.data.message);
          }
        })
        .fail(function () {
          reject("AJAX request failed");
        });
    });
  }
  getContentWarningData()
    .then(function (activities) {
      ajaxResponse = activities;
      activities.forEach(function (activity) {
        appendContentWarningToParent(activity);
      });
    })
    .catch(function (error) {
      console.error("Error occurred:", error);
    });

  // function appendContentWarningToParent(activity) {
  //   var parentElements = jQuery(
  //     '[data-parent-activity-id="' + activity.activity_id + '"]'
  //   );
  //   if (parentElements.length) {
  //     parentElements.each(function () {
  //       console.log("this", jQuery(this));
 
  //       if (jQuery(this).attr("data-attachment-id")) {
  //         console.log("this", jQuery(this));
  //         jQuery(this).addClass("deaddove-media-warning");
  //       }
  //     });
  //   }
  // }
  jQuery(document).on("click", ".deaddove-media-warning", function (event) {
    event.preventDefault();
    event.stopPropagation();
    const mediaContainer = jQuery(this);
      
    event.stopImmediatePropagation();
    jQuery(".media-theatre").hide();
    jQuery(".video-theatre").hide();
    const postParentId = mediaContainer.attr("data-parent-activity-id");
    jQuery(".deaddove-media-modal-wrapper").each(function () {
      const modalWrapper = jQuery(this);
      const modal = modalWrapper.find(".deaddove-modal");
      const showContentButton = modalWrapper.find(".deaddove-show-content-btn");
      const hideContentButton = modalWrapper.find(".deaddove-hide-content-btn");
      const descriptionText = modalWrapper.find(".description-text");
      const currentActivity = ajaxResponse.find((activity) => {
        return activity.activity_id === Number(postParentId);
      });
      
      descriptionText.text(
        currentActivity.content_warning_description ||
          "This content requires your agreement to view."
      );
      modal.show();
      showContentButton.on("click", function () {
        modal.hide();
        jQuery(".deaddove-modal").hide();
        mediaContainer.removeClass("deaddove-media-warning");
      });
      hideContentButton.on("click", function () {
        modal.hide();
        jQuery(".deaddove-modal").hide();
      });
    });
  });

  jQuery(document).ready(async function ($) {
    try {
      const response = await fetchWidgetData();
      const userTags = response.data.user_content_warning_tag;
      let formHtml = `
        <form method="POST" action="" id="description-form" style="margin-top:10px;">
        <div class="accordion-header" style="cursor: pointer; font-weight: bold; background: #f1f1f1; padding: 10px; border: 1px solid #ddd;">
          Content Warnings <span style="float: right;">&#9660;</span>
        </div>
        <div class="accordion-content" style="display: none; padding: 10px; border: 1px solid #ddd;">
      `;

      if (userTags && userTags.length > 0) {
        userTags.forEach(function (tag) {
          formHtml += `
            <label>
              <input type="checkbox" name="tags[]" value="${tag}">
              ${tag}
            </label>
            <br>
          `;
        });
      } else {
        formHtml += `<p>No tags found.</p>`;
      }

      formHtml += `
        <span id="tagErrorMessage" style="color: red; display: none;">Please select at least one tag</span>
        <textarea id="selected_text" style="display:none;" name="selected_text"></textarea>
        <span id="description_not_select_errorMessage" style="color: red; display: none;">Please select a description</span>
        <br><br>
        <button type="submit" id="submit_description" value="Save Description">Submit</button>
        <span id="blur-featured-image-message1" style="color: green; display: none;">Saved!</span>
        </div>
      </form>
      `;

      const CategorySetting = $("#sap-widget-container");
      CategorySetting.append(formHtml);
      $(".accordion-header").click(function () {
        $(".accordion-content").slideToggle(300); 
        const icon = $(this).find("span");
        icon.html(icon.html() === "&#9660;" ? "&#9650;" : "&#9660;"); 
      });
      var widget = document.querySelector(".widget.widget_custom_user_widget");
      let selectContainer = document.querySelector(".sap-editable-area");

      var selectedTextArea = $("#selected_text");
      if (selectContainer) {
        selectContainer.addEventListener("mouseup", function () {
          var selectedText = window.getSelection().toString();
          if (selectedText.length > 0) {
            selectedTextArea.val(selectedText);
            CategorySetting.addClass("toggled");
            $(".toggle-sap-widgets").addClass("active");
          }
        });

        $("#description-form").on("submit", function (e) {
          e.preventDefault();
          var selectedText = selectedTextArea.val().trim();
          let tagsChecked = $("input[name='tags[]']:checked").length > 0;
          if (selectedText === "" || !tagsChecked) {
            if (!tagsChecked) {
              $("#tagErrorMessage").css("display", "block");
            } else {
              $("#tagErrorMessage").css("display", "none");
            }
            if (selectedText === "") {
              $("#description_not_select_errorMessage").show();
            } else {
              $("#description_not_select_errorMessage").hide();
            }
            return;
          } else {
            var checkedTags = [];
            $("input[name='tags[]']:checked").each(function () {
              checkedTags.push($(this).val());
            });
            var tagsAttribute = checkedTags.join(", ");
            var editableArea = document.querySelector(".sap-editable-area");
            if (editableArea) {
              var pTags = editableArea.querySelectorAll("p");
              pTags.forEach(function (pTag) {
                var existingText = pTag.textContent.trim();

                if (existingText.includes(selectedText)) {
                  var updatedHTML = existingText.replace(
                    selectedText,
                    `<p class="deaddove-block-description" tags="${tagsAttribute}">${selectedText}</p><p></p>`
                  );
                  pTag.innerHTML = updatedHTML;
                }
              });
              $("#blur-featured-image-message1").show();
            }
            $("#blur-featured-image-message1").show();
          }
        });
      }
    } catch (error) {
      console.error("Error fetching widget data:", error);
    }
    function fetchWidgetData() {
      return new Promise(function (resolve, reject) {
        jQuery.ajax({
          url: deaddove_ajax.ajaxurl,
          type: "POST",
          data: {
            action: "get_custom_widget",
            nonce: deaddove_ajax.nonce,
          },
          success: function (response) {
            resolve(response);
          },
          error: function (error) {
            reject(error);
          },
        });
      });
    }

  });
    
  
  let observer = new MutationObserver(function (mutations) {
    // videoContainer.append(newDiv);
    // activityContainer.append(newDiv);
    mutations.forEach(function (mutation) {
      if(ajaxResponse!==null){
        ajaxResponse.forEach(function (activity) {
          appendContentWarningToParent(activity);
        });
      }
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });
  function appendContentWarningToParent(activity) {
    var parentElements = jQuery(
      '[data-parent-activity-id="' + activity.activity_id + '"]'
    );
    if (parentElements.length) {
      parentElements.each(function () {
        if (jQuery(this).attr("data-attachment-id")) {
          jQuery(this).addClass("deaddove-media-warning");
        }
      });
    }
  }
    
});
// document.addEventListener("DOMContentLoaded", function () {
//   let className = "custom-class"; 
  
//   let observer = new MutationObserver(function (mutations) {
//     mutations.forEach(function (mutation) {
//       let containers = document.querySelectorAll(".bb-item-thumb");  
//       containers.forEach(function (container) {
//         if (!container.classList.contains(className)) {
//           container.classList.add(className);
//         }
//       });
//     });
//   });
 
   
//   observer.observe(document.body, { childList: true, subtree: true });
 
   
//   let savedClass = localStorage.getItem("custom-class");
//   if (savedClass) {
//     setTimeout(() => {
//       let containers = document.querySelectorAll(".bb-item-thumb");
//       containers.forEach(function (container) {
//         container.classList.add(savedClass);
//       });
//     }, 1000);  
//   }
// });