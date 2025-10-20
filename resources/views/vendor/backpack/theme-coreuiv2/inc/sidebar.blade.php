@if (backpack_auth()->check())
    {{-- Left side column. contains the sidebar --}}
    <div class="{{ backpack_theme_config('classes.sidebar') }}">
      {{-- sidebar: style can be found in sidebar.less --}}
      <nav class="sidebar-nav overflow-hidden">
        {{-- sidebar menu: : style can be found in sidebar.less --}}
        <ul class="nav">
          {{-- <li class="nav-title">{{ trans('backpack::base.administration') }}</li> --}}
          {{-- ================================================ --}}
          {{-- ==== Recommended place for admin menu items ==== --}}
          {{-- ================================================ --}}

          @include(backpack_view('inc.sidebar_content'))

          {{-- ======================================= --}}
          {{-- <li class="divider"></li> --}}
          {{-- <li class="nav-title">Entries</li> --}}
        </ul>
      </nav>
      {{-- /.sidebar --}}
    </div>
@endif

@push('before_scripts')
  <script type="text/javascript">
    // Save default sidebar class
    let sidebarClass = (document.body.className.match(/sidebar-(sm|md|lg|xl)-show/) || ['sidebar-lg-show'])[0];
    let sidebarTransition = function(value) {
        document.querySelector('.app-body > .sidebar').style.transition = value || '';
    };

    // Recover sidebar state
    let sessionState = sessionStorage.getItem('sidebar-collapsed');
    if (sessionState) {
      // disable the transition animation temporarily, so that if you're browsing across
      // pages with the sidebar closed, the sidebar does not flicker into the view
      sidebarTransition("none");
      document.body.classList.toggle(sidebarClass, sessionState === '1');

      // re-enable the transition, so that if the user clicks the hamburger menu, it does have a nice transition
      setTimeout(sidebarTransition, 100);
    }
  </script>
@endpush

@push('after_scripts')
  <script>
      // Store sidebar state
      document.querySelectorAll('.sidebar-toggler').forEach(function(toggler) {
        toggler.addEventListener('click', function() {
          sessionStorage.setItem('sidebar-collapsed', Number(!document.body.classList.contains(sidebarClass)))
          // wait for the sidebar animation to end (250ms) and then update the table headers because datatables uses a cached version
          // and dont update this values if there are dom changes after the table is draw. The sidebar toggling makes
          // the table change width, so the headers need to be adjusted accordingly.
          setTimeout(function() {
            if(typeof crud !== "undefined" && crud.table) {
              crud.table.fixedHeader.adjust();
            }
          }, 300);
        })
      });
      
      // Set active state on menu element - IMPROVED LOGIC
      var full_url = "{{ Request::fullUrl() }}";
      var current_path = "{{ Request::path() }}";
      var $navLinks = $(".sidebar-nav li a, .app-header li a");

      // First look for an exact match including the search string
      var $curentPageLink = $navLinks.filter(
          function() { return $(this).attr('href') === full_url; }
      );

      // If not found, look for the most specific match
      if(!$curentPageLink.length > 0){
          // Get all links that could potentially match
          var potentialMatches = $navLinks.filter( function() {
            var linkHref = $(this).attr('href');
            if (!linkHref) return false;
            
            // Extract path from href
            var linkPath = linkHref.replace(/^https?:\/\/[^\/]+/, '').replace(/\/$/, '');
            var currentPathClean = current_path.replace(/\/$/, '');
            
            // Check if current path starts with link path
            if (currentPathClean.startsWith(linkPath)) {
              return true;
            }
            
            return false;
          });
          
          // Find the most specific match (longest path)
          if (potentialMatches.length > 0) {
            var longestMatch = potentialMatches[0];
            var longestLength = $(longestMatch).attr('href').replace(/^https?:\/\/[^\/]+/, '').replace(/\/$/, '').length;
            
            potentialMatches.each(function() {
              var linkLength = $(this).attr('href').replace(/^https?:\/\/[^\/]+/, '').replace(/\/$/, '').length;
              if (linkLength > longestLength) {
                longestMatch = this;
                longestLength = linkLength;
              }
            });
            
            $curentPageLink = $(longestMatch);
          }
      }

      // for the found links that can be considered current, make sure
      // - the parent item is open
      $curentPageLink.parents('li').addClass('open');
      // - the actual element is active
      $curentPageLink.each(function() {
        $(this).addClass('active');
      });
      
      // Additional fix: Remove active class from conflicting dropdown items
      $('.dropdown-item').each(function() {
        var $item = $(this);
        var itemHref = $item.attr('href');
        
        if (itemHref) {
          var itemPath = itemHref.replace(/^https?:\/\/[^\/]+/, '').replace(/\/$/, '');
          var currentPathClean = current_path.replace(/\/$/, '');
          
          // If this item is not the current page, remove active class
          if (!currentPathClean.startsWith(itemPath) || currentPathClean !== itemPath) {
            $item.removeClass('active');
          }
        }
      });
  </script>
@endpush
