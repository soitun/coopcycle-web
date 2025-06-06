context('Delivery via form (payment options: Stripe or Cash)', () => {
  beforeEach(() => {
    cy.loadFixtures('../cypress/fixtures/stores.yml')
    cy.terminal('echo CASH_ON_DELIVERY_ENABLED=1 >> .env.test')
  })
  afterEach(() => {
    cy.terminal(`sed -i '/CASH_ON_DELIVERY_ENABLED=1/d' .env.test`)
  })

  it('should create a delivery with cash payment', () => {
    cy.visit('/fr/embed/delivery/start')

    // Pickup

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(1)',
      '91 rue de rivoli paris',
      /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
    )

    // Dropoff

    cy.searchAddress(
      '[data-form="task"]:nth-of-type(2)',
      '120 rue st maur paris',
      /^120,? Rue Saint-Maur,? 75011,? Paris,? France/i,
    )

    cy.get('[data-form="task"]').each($el => {
      cy.wrap($el)
        .find('[id$="address_newAddress_latitude"]')
        .invoke('val')
        .should('match', /[0-9.]+/)
      cy.wrap($el)
        .find('[id$="address_newAddress_longitude"]')
        .invoke('val')
        .should('match', /[0-9.]+/)
    })

    cy.get('#delivery_name').type('John Doe', { timeout: 5000, delay: 30 })
    cy.get('#delivery_email').type('dev@coopcycle.org', {
      timeout: 5000,
      delay: 30,
    })
    cy.get('#delivery_telephone').type('0612345678', {
      timeout: 5000,
      delay: 30,
    })

    cy.get('form[name="delivery"]').submit()

    cy.urlmatch(/\/fr\/forms\/[a-zA-Z0-9]+\/summary/)

    cy.get('.alert-info')
      .invoke('text')
      .should('match', /Vous avez demandé une course qui vous sera déposée le/)

    cy.get('[data-testid="pm.cash"] button').click()

    cy.get('form[name="checkout_payment"]').submit()

    cy.urlmatch(/\/fr\/pub\/o\/[a-zA-Z0-9]+/)
  })
})
