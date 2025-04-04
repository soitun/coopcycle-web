import React, { StrictMode } from 'react'
import { render } from '../../utils/react'
import { createRoot } from 'react-dom/client'
import Sortable from 'sortablejs'
import { I18nextProvider, useTranslation } from 'react-i18next'

import './pricing-rules.scss'
import {
  parsePriceAST,
  PriceRange,
  FixedPrice,
  PricePerPackage,
} from './pricing-rule-parser'
import i18n from '../../i18n'
import PricingRuleTarget from './components/PricingRuleTarget'
import AddRulePerDelivery from './components/AddRulePerDelivery'
import RulePicker from './components/RulePicker'
import PriceRangeEditor from './components/PriceRangeEditor'
import PricePerPackageEditor from './components/PricePerPackageEditor'
import LegacyPricingRulesWarning from './components/LegacyPricingRulesWarning'
import AddRulePerTask from './components/AddRulePerTask'

const PriceChoice = ({ defaultValue, onChange }) => {
  const { t } = useTranslation()

  return (
    <select
      data-testid="pricing_rule_price_type_choice"
      onChange={e => onChange(e.target.value)}
      defaultValue={defaultValue}>
      <option value="fixed">{t('PRICE_RANGE_EDITOR.TYPE_FIXED')}</option>
      <option value="range">{t('PRICE_RANGE_EDITOR.TYPE_RANGE')}</option>
      <option value="per_package">
        {t('PRICE_RANGE_EDITOR.TYPE_PER_PACKAGE')}
      </option>
    </select>
  )
}

const ruleSet = $('#rule-set'),
  warning = $('form[name="pricing_rule_set"] .alert-warning')

const wrapper = document.getElementById('rule-set')

const zones = JSON.parse(wrapper.dataset.zones)
const packages = JSON.parse(wrapper.dataset.packages)

const onListChange = () => {
  if ($('.delivery-pricing-ruleset > li').length === 0) {
    warning.removeClass('hidden')
  } else {
    warning.addClass('hidden')
  }

  $('.delivery-pricing-ruleset > li').each((index, el) => {
    $(el).find('.delivery-pricing-ruleset__rule__position').val(index)
    $(el).attr('data-testid', `pricing-rule-${index}`)
  })
}

new Sortable(document.querySelector('.delivery-pricing-ruleset'), {
  group: 'rules',
  handle: '.delivery-pricing-ruleset__rule__handle',
  animation: 250,
  onUpdate: onListChange,
})

function renderPriceTypeItem(
  $input,
  editorRoot,
  priceType,
  priceRangeDefaultValue,
  pricePerPackageDefaultValue,
) {
  switch (priceType) {
    case 'range':
      $input.addClass('d-none')

      editorRoot.render(
        <PriceRangeEditor
          defaultValue={priceRangeDefaultValue}
          onChange={({ attribute, price, step, threshold }) => {
            $input.val(
              `price_range(${attribute}, ${price}, ${step}, ${threshold})`,
            )
          }}
        />,
      )

      break
    case 'per_package':
      $input.addClass('d-none')

      editorRoot.render(
        <PricePerPackageEditor
          defaultValue={pricePerPackageDefaultValue}
          onChange={({ packageName, unitPrice, offset, discountPrice }) => {
            $input.val(
              `price_per_package(packages, "${packageName}", ${unitPrice}, ${offset}, ${discountPrice})`,
            )
          }}
          packages={packages}
        />,
      )
      break
    case 'fixed':
    default:
      editorRoot.render(null)

      $input.removeClass('d-none')
  }
}

const renderPriceChoice = item => {
  const $label = $(item).find('.delivery-pricing-ruleset__rule__price__label')
  const $input = $(item).find('.delivery-pricing-ruleset__rule__price__input')

  const priceAST = $(item).data('priceExpression')
  const expression = $input.val()

  const price = priceAST
    ? parsePriceAST(priceAST, expression)
    : new FixedPrice(0)

  let priceType = 'fixed'

  let priceRangeDefaultValue = {}
  let pricePerPackageDefaultValue = {}

  if (price instanceof PriceRange) {
    priceType = 'range'
    priceRangeDefaultValue = price
  }

  if (price instanceof PricePerPackage) {
    priceType = 'per_package'
    pricePerPackageDefaultValue = price
  }

  const $parent = $input.parent()

  // Check if parent already has a React root container
  let editorContainer = $parent.find('[data-react-root-container="true"]')
  if (editorContainer.length === 0) {
    // Create a new container if none exists
    editorContainer = $('<div data-react-root-container="true" />')
    editorContainer.appendTo($parent)
  }
  const editorRoot = createRoot(editorContainer[0])

  render(
    <I18nextProvider i18n={i18n}>
      <PriceChoice
        defaultValue={priceType}
        onChange={value => {
          renderPriceTypeItem(
            $input,
            editorRoot,
            value,
            priceRangeDefaultValue,
            pricePerPackageDefaultValue,
          )
        }}
      />
    </I18nextProvider>,
    $label[0],
  )

  renderPriceTypeItem(
    $input,
    editorRoot,
    priceType,
    priceRangeDefaultValue,
    pricePerPackageDefaultValue,
  )
}

function hydrate(item, { ruleTarget, expression, expressionAST }) {
  const ruleTargetContainer = $(item).find(
    '.delivery-pricing-ruleset__rule__target__container',
  )
  render(<PricingRuleTarget target={ruleTarget} />, ruleTargetContainer[0])

  let $expressionInput = $(item).find(
    '.delivery-pricing-ruleset__rule__expression input',
  )
  function onExpressionChange(newExpression) {
    $expressionInput.val(newExpression)
  }
  render(
    <RulePicker
      ruleTarget={ruleTarget}
      zones={zones}
      packages={packages}
      expression={expression}
      expressionAST={expressionAST}
      onExpressionChange={onExpressionChange}
    />,
    $(item).find('.rule-expression-container')[0],
  )

  const priceEl = $(item).find('.delivery-pricing-ruleset__rule__price')
  renderPriceChoice(priceEl)
}

function addPricingRule(ruleTarget) {
  let newRule = ruleSet.attr('data-prototype')
  newRule = newRule.replace(/__name__/g, ruleSet.find('li').length)

  let newLi = $('<li></li>')
    .addClass('delivery-pricing-ruleset__rule')
    .html(newRule)

  let targetInput = newLi.find('.delivery-pricing-ruleset__rule__target input')
  targetInput.val(ruleTarget)

  hydrate(newLi, {
    ruleTarget,
    expression: undefined,
    expressionAST: undefined,
  })

  if (ruleTarget === 'TASK') {
    //add at the beginning of the list (because task based rules are evaluated first)
    newLi.prependTo(ruleSet)
  } else if (ruleTarget === 'DELIVERY') {
    //add at the end of the list
    newLi.appendTo(ruleSet)
  }

  onListChange()
}

$(document).on(
  'click',
  '.delivery-pricing-ruleset__rule__remove > a',
  function (e) {
    e.preventDefault()
    $(e.target).closest('li').remove()

    onListChange()
  },
)

$('.delivery-pricing-ruleset__rule').each(function (index, item) {
  const ruleTarget = $(item)
    .find('.delivery-pricing-ruleset__rule__target input')
    .val()

  const expression = $(item)
    .find('.delivery-pricing-ruleset__rule__expression input')
    .val()
  const expressionAST = $(item)
    .find('.delivery-pricing-ruleset__rule__expression')
    .data('expression')

  hydrate(item, { ruleTarget, expression, expressionAST })
})

function migrateToTarget(ruleTarget) {
  $('.delivery-pricing-ruleset__rule').each(function (index, item) {
    const ruleTargetInput = $(item).find(
      '.delivery-pricing-ruleset__rule__target input',
    )

    const currentRuleTarget = ruleTargetInput.val()

    if (currentRuleTarget === 'LEGACY_TARGET_DYNAMIC') {
      ruleTargetInput.val(ruleTarget)

      const expression = $(item)
        .find('.delivery-pricing-ruleset__rule__expression input')
        .val()
      const expressionAST = $(item)
        .find('.delivery-pricing-ruleset__rule__expression')
        .data('expression')

      hydrate(item, { ruleTarget, expression, expressionAST })
    }
  })
}

function hasLegacyRules() {
  let targets = []

  $('.delivery-pricing-ruleset__rule').each(function (index, item) {
    const ruleTarget = $(item)
      .find('.delivery-pricing-ruleset__rule__target input')
      .val()
    targets.push(ruleTarget)
  })

  const legacyRuleTarget = targets.find(
    target => target === 'LEGACY_TARGET_DYNAMIC',
  )

  return legacyRuleTarget !== undefined
}

$('#pricing-rule-set-header').each(function (index, item) {
  const root = createRoot(item)
  root.render(
    <StrictMode>
      {hasLegacyRules() && (
        <LegacyPricingRulesWarning
          migrateToTarget={ruleTarget => {
            root.unmount()
            migrateToTarget(ruleTarget)
          }}
        />
      )}
      <div className="d-flex justify-content-end">
        <AddRulePerTask onAddRule={addPricingRule} />
      </div>
    </StrictMode>,
  )
})

$('#pricing-rule-set-footer').each(function (index, item) {
  render(
    <div className="mb-5 d-flex justify-content-end">
      <AddRulePerDelivery onAddRule={addPricingRule} />
    </div>,
    item,
  )
})
