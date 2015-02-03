<article  class="content typography">
    $Content
    $Form
    <div class="search-bar">
        <hr/>
        $SearchForm
        <hr/>
    </div>
    <div class="video-gallery">
        <div class='pager-content'>
            <% loop $Videos %>
            $Me
            <% end_loop %>
        </div>
    </div>
</article>