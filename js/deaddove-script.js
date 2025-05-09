document.addEventListener("DOMContentLoaded", function () {
  const modalWrappers = document.querySelectorAll(".deaddove-modal-wrapper");

  modalWrappers.forEach(function (wrapper) {
    const modal = wrapper.querySelector(".deaddove-modal");
    const blurredContent = wrapper.querySelector(".deaddove-blurred-content");
    const settingsLink = wrapper.querySelector(".deaddove-settings-link");

    // Open the modal when blurred content is clicked
    if (blurredContent) {
      blurredContent.addEventListener("click", function () {
        const blurredRect = blurredContent.getBoundingClientRect();
        const scrollTop =
          window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft =
          window.pageXOffset || document.documentElement.scrollLeft;
        modal.style.top = `${blurredRect.top + scrollTop}px`;
        modal.style.left = `${blurredRect.left + scrollLeft}px`;
        modal.style.display = "flex";
      });
    }

    // Use event delegation for modal buttons
    wrapper.addEventListener("click", function (event) {
      if (event.target.classList.contains("deaddove-show-content-btn")) {
        // User agreed to view the content
        modal.style.display = "none";
        blurredContent.classList.remove("deaddove-blur");
        blurredContent.style.pointerEvents = "none";
      } else if (event.target.classList.contains("deaddove-hide-content-btn")) {
        // User chose to keep the content hidden
        modal.style.display = "none";
      }
    });

    // Handle the settings link correctly
    if (settingsLink) {
      settingsLink.addEventListener("click", function (event) {
        event.preventDefault(); // Prevent default link behavior
        window.location.href =
          "/wp-admin/profile.php#deaddove-warning-settings"; // Navigate to the settings page
      });
    }
  });
  // jQuery(document).ready(function ($) {
  //   console.log("hello demo");
  //   var hasModified = false;
  //   if ($(".deaddove-block-description").length > 0) {
  //     hasModified = true;
  //   }
  //   console.log(hasModified);
  //   $(document).on(
  //     "click",
  //     ".sap-action-button, .sap-story-publish-btn, .sap-story-draft-btn, .sap-story-update-btn, .sap-story-draft-btn, .sap-story-preview-btn",
  //     function (event) {
  //       event.preventDefault();
  //       if ($(".deaddove-block-description").length > 0) {
  //         console.log("Element found!");
  //         hasModified = true;
  //       }

  //       $(".deaddove-block-description").each(function () {
  //         var tagsValue = $(this).attr("tags");
  //         var content = $(this).html();
  //         if (tagsValue) {
  //           var replacement = `[content_warning tags="${tagsValue}"]${content}[/content_warning]`;
  //           $(this).replaceWith(replacement);
  //         }
  //       });

  //       console.log("P tag replaced with content_warning shortcode.");

  //       var button = this;
  //       if (hasModified) {
  //         console.log("yess inside thie method");
  //         setTimeout(function () {
  //           var href = $(button).attr("href");
  //           if (href && href !== "#") {
  //             window.location.href = href;
  //           } else {
  //             console.log("hello demo;::::::::::::  ");
  //             $(button).trigger("click");
  //           }
  //         }, 500);
  //       }
  //     }
  //   );
  // });
  
  jQuery(document).ready(function ($) {
    console.log("hello demo");

    var hasModified = false;
    var isProcessing = false; // Flag to prevent multiple clicks

    if ($(".deaddove-block-description").length > 0) {
        hasModified = true;
    }
    
    console.log(hasModified);

    // $(document).on("click", ".sap-action-button, .sap-story-publish-btn, .sap-story-draft-btn, .sap-story-update-btn, .sap-story-preview-btn", function (event) {
    //     if (isProcessing) return false; // Stop multiple clicks
    //     isProcessing = true;

    //     event.preventDefault();

    //     if ($(".deaddove-block-description").length > 0) {
    //         console.log("Element found!");
    //         hasModified = true;
    //     }

    //     $(".deaddove-block-description").each(function () {
    //         var tagsValue = $(this).attr("tags");
    //         var content = $(this).html();
    //         if (tagsValue) {
    //             var replacement = `[content_warning tags="${tagsValue}"]${content}[/content_warning]`;
    //             $(this).replaceWith(replacement);
    //         }
    //     });

    //     console.log("P tag replaced with content_warning shortcode.");

    //     var button = this;
    //     if (hasModified) {
    //         console.log("Processing redirection...");
    //         setTimeout(function () {
    //             var href = $(button).attr("href");
    //             if (href && href !== "#") {
    //                 window.location.href = href;
    //             } else {
    //                 console.log("hello demo;::::::::::::  ");
    //                 $(button).trigger("click");
    //             }
    //             isProcessing = false; // Allow clicks again after processing
    //         }, 1000);
    //     } else {
    //         isProcessing = false; // Reset flag if no modification happened
    //     }
    // });
     /* 
    manage from frontend wdiget 
    */
    $(".deaddove-blog-warning").on("click", function (event) {
      event.preventDefault(); // Default behavior roko
      
      const blurContent = $(this);
      const blurContentId = blurContent.attr("id");
      const postId = blurContentId.match(/\d+/);  

      if (postId) {
          const postIdNumber = parseInt(postId[0], 10);
          
          ajaxCallingMethod({postIdNumber, blurContent, postType:'post'});
      }
  });
  $(".deaddove-block-description").on("click", function(event){
    const descriptionText = $(this);
    const postParent = descriptionText.closest(".post");
     console.log(postParent)
     const postParentId = postParent.attr("id");
     const postId = postParentId.match(/\d+/);  

     if (postId) {
         const postIdNumber = parseInt(postId[0], 10);
         const tags = descriptionText.attr('tags');
         ajaxCallingMethod({postIdNumber, blurContent:descriptionText, tags, postType:'postDescription'});
     } 
  })
  /* 
  ajax calling helper function 
  */
  function ajaxCallingMethod({postIdNumber, blurContent, tags='', postType = 'post'}){
    console.log("checking tags:",tags);
    
    $.ajax({
      url: deaddove_ajax.ajaxurl,  
      type: "POST",
      data: {
          action: "deaddove_get_post_description",
          post_id: postIdNumber,
          postTags:tags,
          security: deaddove_ajax.nonce,  
      },
      success: function (response) {
          console.log("AJAX Response:", response);
          if (response.success) {
            $(".deaddove-modal-wrapper-multiple-posts").each(function () {
              const wrapper = $(this);
              const modal = wrapper.find(".deaddove-modal");
              const showContentButton = wrapper.find(".deaddove-show-content-btn");
              const hideContentButton = wrapper.find(".deaddove-hide-content-btn");
              const descriptionText = wrapper.find(".description-text");
              descriptionText.text(response.data);
              modal.show();
              showContentButton.on("click", function () {
                modal.hide();
               if(postType ==='post'){
                 blurContent.removeClass("deaddove-blog-warning");
               }else{
                console.log(blurContent,"hellokdsflj");
                blurContent.removeClass("deaddove-block-description");
               }
              blurContent.off("click");
              });
              hideContentButton.on("click", function () {
                modal.hide();
              });
            });
          } else {
              alert("Error: " + response.data);
          }
      },
      error: function (xhr, status, error) {
          console.error("AJAX Error:", error);
      },
  });
  }
});

});
console.log("checking file file running or not ");
