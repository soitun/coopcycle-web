var Encore = require('@symfony/webpack-encore')

Encore
  .configureRuntimeEnvironment('dev')
  .setOutputPath('web/')
  .setPublicPath('/')

  .addStyleEntry('css/styles', './assets/css/main.scss')
  .addStyleEntry('css/tracking', './assets/css/tracking.scss')

  .addEntry('js/dashboard', './js/app/dashboard/index.jsx')
  .addEntry('js/homepage', './js/app/homepage/index.js')
  .addEntry('js/restaurant-list', './js/app/restaurant-list/index.jsx')
  .addEntry('js/cart', './js/app/cart/index.jsx')
  .addEntry('js/order', './js/app/order/index.jsx')
  .addEntry('js/order-payment', './js/app/order/payment.js')
  .addEntry('js/order-tracking', './js/app/order/tracking.jsx')
  .addEntry('js/profile-deliveries', './js/app/profile/deliveries.js')
  .addEntry('js/delivery-form', './js/app/delivery/form.jsx')
  .addEntry('js/delivery-list', './js/app/delivery/list.jsx')
  .addEntry('js/delivery-pricing-rules', './js/app/delivery/pricing-rules.jsx')
  .addEntry('js/restaurant-form', './js/app/restaurant/form.jsx')
  .addEntry('js/restaurant-menu', './js/app/restaurant/menu.jsx')
  .addEntry('js/restaurant-planning', './js/app/restaurant/planning.jsx')
  .addEntry('js/restaurant-panel', './js/app/restaurant/panel.jsx')
  .addEntry('js/restaurants-map', './js/app/restaurants-map/index.jsx')
  .addEntry('js/tracking', './js/app/tracking/index.jsx')
  .addEntry('js/user-tracking', './js/app/user/tracking.jsx')
  .addEntry('js/widgets/opening-hours-parser', './js/app/widgets/OpeningHoursParser.jsx')
  .addEntry('js/widgets/opening-hours-input', './js/app/widgets/OpeningHoursInput.jsx')
  .addEntry('js/widgets/address-input', './js/app/widgets/AddressInput.jsx')
  .addEntry('js/widgets/search', './js/app/widgets/Search.jsx')
  .addEntry('js/zone-preview', './js/app/zone/preview.jsx')

  .enablePostCssLoader()
  .enableSassLoader(function(sassOptions) {}, {
    resolveUrlLoader: false
  })
  .enableReactPreset()

  .autoProvidejQuery()
  .enableSourceMaps(true)

  // empty the outputPath dir before each build
  // .cleanupOutputBeforeBuild()
  // show OS notifications when builds finish/fail
  // .enableBuildNotifications()
  // create hashed filenames (e.g. app.abc123.css)
  // .enableVersioning()


let webpackConfig = Encore.getWebpackConfig()

webpackConfig.plugins.push(
  function() {
    this.plugin("done", function(stats) {
      console.log('okkkk')
      if (stats.compilation.errors && stats.compilation.errors.length && process.argv.indexOf('--watch') == -1) {
        console.log('PLOP')
        // console.log(stats.compilation.errors);
        // process.exit(1); // or throw new Error('webpack build failed.');
      }
    })
  }
)

module.exports = webpackConfig
