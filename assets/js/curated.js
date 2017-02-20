jQuery(document).ready(function($){

    var $selectPost = $(".js-data-example-ajax");
    
    $selectPost.select2({
      ajax: {
        url: '/wp-json/wp/v2/posts',
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            search: params.term, // search term
            page: params.page
          };
        },
        processResults: function (data, params) {
          // parse the results into the format expected by Select2
          // since we are using custom formatting functions we do not need to
          // alter the remote JSON data, except to indicate that infinite
          // scrolling can be used
          params.page = params.page || 1;
    
          return {
            results: data,
            pagination: {
              more: (params.page * 30) < data.total_count
            }
          };
        },
        cache: true
      },
      escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
      minimumInputLength: 1,
      templateResult: formatPost, 
      templateSelection: formatPostSelection 
    });
    
    function niceDate(raw) {    
        return new Date(raw).toLocaleDateString('en-GB', {
            day : 'numeric',
            month : 'long',
            year : 'numeric'
        });
    }
    
    function formatPost (post) {
      if (post.loading) return post.text;

      return "<div class='select2-result-post clearfix'>" +
        "<div class='select2-result-post__meta'>" +
          "<div class='select2-result-post__title'>" + post.title.rendered + "</div>" +
          "<div class='select2-result-post__date'>" + niceDate(post.date) + "</div>" +
        "</div></div>";
    }

    function formatPostSelection (post) {
      //return post.title.rendered;
      return 'Select posts to add to curated list below:';
    }


    $selectPost.on("select2:select", function (e) { 
        var $el = e.params.data;
        var post_id = $el.id;
		var $selected = $(this).find("option:selected");
		var $table = $(".curated-posts-table");
		$table.find(" > tbody tr.curated-placeholder").hide();
		if ( $selected.val() == 0 ) {
			$selected.siblings("option:enabled").each(function() {
				$table.find(" > tbody:last").append("<tr><td class=\"icon\"><span class=\"dashicons dashicons-menu post-state-format\"></span></td><td><input type=\"hidden\" name=\"curated_posts[]\" value=\""+$el.id+"\">"+$el.title.rendered+"</td><td>"+niceDate($el.date)+"</td><td><a href=\"#\" class=\"dashicons dashicons-no-alt curated-delete\"></a></td></tr>");
				$(this).prop("disabled", true);
			});
		} else {
			$table.find(" > tbody:last").append("<tr><td class=\"icon\"><span class=\"dashicons dashicons-menu post-state-format\"></span></td><td><input type=\"hidden\" name=\"curated_posts[]\" value=\""+$el.id+"\">"+$el.title.rendered+"</td><td>"+niceDate($el.date)+"</td><td><a href=\"#\" class=\"dashicons dashicons-no-alt curated-delete\"></a></td></tr>");
			$selected.siblings("[value=0]").each(function() {
				console.log($(this).siblings("option:enabled").length);
				$(this).prop("disabled", $(this).siblings("option:enabled").length <= 1);
			});
		}
		$selected.prop("disabled", true);
		$(this).val("").trigger("select2:updated");

    });
    
	// Apply select2 class for each post in dropdown
	$(".curated-posts-table input[name=\"curated_posts[]\"]").each(function() {
		$(".curated-posts option[value=\""+$(this).val()+"\"]").prop("disabled", true);
		$(".curated-posts").trigger("select2:updated");
	});

	// Add post to group
	$(".curated-posts").on("change", function(evt, params) {
		post_id = params.selected;
		$selected = $(this).find("option:selected");
		$table = $(".curated-posts-table");
		$table.find(" > tbody tr.curated-placeholder").hide();
		if ( $selected.val() == 0 ) {
			$selected.siblings("option:enabled").each(function() {
				$table.find(" > tbody:last").append("<tr><td class=\"icon\"><span class=\"dashicons dashicons-menu post-state-format\"></span></td><td><input type=\"hidden\" name=\"curated_posts[]\" value=\""+$(this).val()+"\">"+$(this).text()+"</td><td>"+$(this).data("post-type")+"</td><td><a href=\"#\" class=\"dashicons dashicons-no-alt curated-delete\"></a></td></tr>");
				$(this).prop("disabled", true);
			});
		} else {
			$table.find(" > tbody:last").append("<tr><td class=\"icon\"><span class=\"dashicons dashicons-menu post-state-format\"></span></td><td><input type=\"hidden\" name=\"curated_posts[]\" value=\""+post_id+"\">"+$selected.text()+"</td><td>"+$selected.data("post-type")+"</td><td><a href=\"#\" class=\"dashicons dashicons-no-alt curated-delete\"></a></td></tr>");
			$selected.siblings("[value=0]").each(function() {
				console.log($(this).siblings("option:enabled").length);
				$(this).prop("disabled", $(this).siblings("option:enabled").length <= 1);
			});
		}
		$selected.prop("disabled", true);
		$(this).val("").trigger("select2:updated");
	});

	// Make posts sortable and removable
	$(".curated-posts-table > tbody").sortable({
		axis: "y"
	}).on("click", ".curated-delete", function(e) {
	    e.preventDefault();

		$row = $(this).closest("tr");
		$id = $row.find("input[name=\"curated_posts[]\"]").val();
		$(".curated-posts option[value=\""+$id+"\"]").prop("disabled", false).siblings("[value=0]").prop("disabled", false);
		$(".curated-posts").trigger("select2:updated");
		$row.remove();
		$table = $(".curated-posts-table");
		if ( $table.find(" > tbody tr:visible").length == 0 ) $table.find(" > tbody tr.curated-placeholder").show();
	});

});
