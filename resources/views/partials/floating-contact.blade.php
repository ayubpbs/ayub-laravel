{{--
  Floating WhatsApp + Messenger buttons (Home Page only).
  Values come from config/shop.php (set SHOP_WHATSAPP and SHOP_MESSENGER_USERNAME in .env).
  Include with: @include('partials.floating-contact')
--}}
@php
  $waNumber = preg_replace('/\D+/', '', (string) config('shop.whatsapp_number'));
  $fbUser   = trim((string) config('shop.messenger_username'));
  $waText   = rawurlencode('Hello ' . config('shop.name') . ', I need some help.');
@endphp

@if ($waNumber !== '' || $fbUser !== '')
<style>
  .fc-wrap{ position:fixed; right:16px; bottom:90px; z-index:40; display:flex; flex-direction:column; gap:12px; }
  .fc-btn{ width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center;
           color:#fff; text-decoration:none; box-shadow:0 4px 12px rgba(0,0,0,.25);
           transition:transform .2s ease, box-shadow .2s ease; }
  .fc-btn:hover, .fc-btn:focus-visible{ transform:translateY(-3px) scale(1.08); box-shadow:0 8px 18px rgba(0,0,0,.3); color:#fff; }
  .fc-btn svg{ width:30px; height:30px; fill:currentColor; display:block; }
  .fc-whatsapp{ background:#25D366; }
  .fc-messenger{ background:linear-gradient(135deg,#00B2FF,#006AFF); }
  @media (max-width:600px){
    .fc-wrap{ right:12px; bottom:84px; gap:10px; }
    .fc-btn{ width:48px; height:48px; }
    .fc-btn svg{ width:26px; height:26px; }
  }
  @media (prefers-reduced-motion:reduce){ .fc-btn{ transition:none; } .fc-btn:hover{ transform:none; } }
</style>

<div class="fc-wrap">
  @if ($waNumber !== '')
    <a class="fc-btn fc-whatsapp" href="https://wa.me/{{ $waNumber }}?text={{ $waText }}"
       target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp" title="Chat on WhatsApp">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35zM12.04 2C6.5 2 2 6.5 2 12.04c0 1.77.46 3.5 1.34 5.02L2 22l5.06-1.33a10 10 0 0 0 4.98 1.27h.01c5.54 0 10.04-4.5 10.04-10.04 0-2.68-1.04-5.2-2.94-7.1A9.97 9.97 0 0 0 12.04 2z"/></svg>
    </a>
  @endif

  @if ($fbUser !== '')
    <a class="fc-btn fc-messenger" href="https://m.me/{{ rawurlencode($fbUser) }}"
       target="_blank" rel="noopener noreferrer" aria-label="Chat on Messenger" title="Chat on Messenger">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.36 2 2 6.13 2 11.7c0 2.91 1.2 5.42 3.15 7.16.16.14.26.35.27.57l.05 1.78c.02.57.6.94 1.12.71l1.98-.87c.17-.07.35-.09.52-.05.91.25 1.87.38 2.91.38 5.64 0 10-4.13 10-9.7S17.64 2 12 2zm6 7.46l-2.94 4.66c-.47.74-1.47.93-2.17.4l-2.34-1.75a.6.6 0 0 0-.72 0l-3.16 2.4c-.42.32-.97-.19-.69-.64l2.94-4.66c.47-.74 1.47-.93 2.17-.4l2.34 1.75a.6.6 0 0 0 .72 0l3.16-2.4c.42-.32.97.18.69.64z"/></svg>
    </a>
  @endif
</div>
@endif
