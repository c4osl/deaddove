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
  

  jQuery(document).ready(function ($) {
    console.log("Dead Dove script loaded");

    var hasModified = false;
    var isProcessing = false;

    if ($(".deaddove-block-description").length > 0) {
        hasModified = true;
    }
    
    console.log("Has modified:", hasModified);

    /* 
    Manage frontend widget 
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
