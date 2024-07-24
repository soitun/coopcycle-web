context('Delivery', () => {
  beforeEach(() => {
    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd =
      'bin/console coopcycle:fixtures:load -f cypress/fixtures/stores.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)
  })

  it('create delivery as admin', function () {
    cy.visit('/login')

    cy.login('admin', '12345678')

    cy.visit('/admin/stores')

    cy.get('[data-testid=store__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store__list_item]')
      .contains('Créer une livraison')
      .click()

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
    )

    cy.get('[data-form="task"]:nth-of-type(1)')
      .find('[data-testid=address_name__popover_pill]')
      .click()
    cy.get('form[id=delivery_tasks_0_address_name__popover]')
      .find('input')
      .clear()
    cy.get('form[id=delivery_tasks_0_address_name__popover]')
      .find('input')
      .type('Office')
    cy.get('form[id=delivery_tasks_0_address_name__popover]')
      .find('button')
      .click()

    cy.get('[data-form="task"]:nth-of-type(1)')
      .find('[data-testid=address_telephone__popover_pill]')
      .click()
    cy.get('form[id=delivery_tasks_0_address_telephone__popover]')
      .find('input')
      .clear()
    cy.get('form[id=delivery_tasks_0_address_telephone__popover]')
      .find('input')
      .type('+33112121212')
    cy.get('form[id=delivery_tasks_0_address_telephone__popover]')
      .find('button')
      .click()

    cy.get('[data-form="task"]:nth-of-type(1)')
      .find('[data-testid=address_contactName__popover_pill]')
      .click()
    cy.get('form[id=delivery_tasks_0_address_contactName__popover]')
      .find('input')
      .clear()
    cy.get('form[id=delivery_tasks_0_address_contactName__popover]')
      .find('input')
      .type('John Doe')
    cy.get('form[id=delivery_tasks_0_address_contactName__popover]')
      .find('button')
      .click()

    cy.get('#delivery_tasks_0_comments').type('Pickup comments')

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('[data-form="task"]:nth-of-type(2)')
      .find('[data-testid=address_name__popover_pill]')
      .click()
    cy.get('form[id=delivery_tasks_1_address_name__popover]')
      .find('input')
      .clear()
    cy.get('form[id=delivery_tasks_1_address_name__popover]')
      .find('input')
      .type('Warehouse')
    cy.get('form[id=delivery_tasks_1_address_name__popover]')
      .find('button')
      .click()

    cy.get('[data-form="task"]:nth-of-type(2)')
      .find('[data-testid=address_telephone__popover_pill]')
      .click()
    cy.get('form[id=delivery_tasks_1_address_telephone__popover]')
      .find('input')
      .clear()
    cy.get('form[id=delivery_tasks_1_address_telephone__popover]')
      .find('input')
      .type('+33114141414')
    cy.get('form[id=delivery_tasks_1_address_telephone__popover]')
      .find('button')
      .click()

    cy.get('[data-form="task"]:nth-of-type(2)')
      .find('[data-testid=address_contactName__popover_pill]')
      .click()
    cy.get('form[id=delivery_tasks_1_address_contactName__popover]')
      .find('input')
      .clear()
    cy.get('form[id=delivery_tasks_1_address_contactName__popover]')
      .find('input')
      .type('Jane Smith')
    cy.get('form[id=delivery_tasks_1_address_contactName__popover]')
      .find('button')
      .click()

    cy.get('#delivery_tasks_1_weight').clear()
    cy.get('#delivery_tasks_1_weight').type(2.5)

    cy.get('#delivery_tasks_1_comments').type('Dropoff comments')

    cy.get('#delivery-submit').click()

    cy.location('pathname', { timeout: 3000 }).should(
      'match',
      /\/admin\/stores\/[0-9]+\/deliveries$/,
    )
    cy.get('[data-testid=delivery__list_item]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
