context('Delivery (role: dispatcher); store with time slot pricing', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_dispatcher.yml',
      '../features/fixtures/ORM/store_w_time_slot_pricing.yml',
    ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('create delivery order', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une nouvelle commande')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Office',
      '+33112121212',
      'John Doe',
    )

    cy.betaEnterCommentAtPosition(0, 'Pickup comments')

    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121212',
      'Jane smith',
    )
    cy.betaEnterCommentAtPosition(1, 'Dropoff comments')

    cy.betaEnterWeightAtPosition(1, 2.5)

    cy.get('[data-testid="tax-included"]').contains('6,99 €')

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order-total-including-tax"]')
      .find('[data-testid="value"]')
      .contains('€6.99')

    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
  })
})
