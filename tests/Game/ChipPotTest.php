<?php

namespace xLink\Tests\Game;

use xLink\Poker\Client;
use xLink\Poker\Game\ChipPot;
use xLink\Poker\Game\Chips;
use xLink\Poker\Game\Player;
use xLink\Poker\Game\PlayerCollection;

class ChipPotTest extends BaseGameTestCase
{
    public function setUp()
    {
    }

    /** @test */
    public function can_create_a_chipPot_without_chips()
    {
        $chipPot = ChipPot::create();

        $this->assertInstanceOf(ChipPot::class, $chipPot);
        $this->assertEquals(0, $chipPot->total()->amount());
    }

    /** @test */
    public function can_create_a_chipPot_with_chips_and_players()
    {
        // create a client & player
        $client = Client::register('player1', Chips::fromAmount(5500));
        $player = Player::fromClient($client, Chips::fromAmount(1000));

        // create the pot
        $chipPot = ChipPot::create();

        // add the chips
        $chipPot = $chipPot->addChips(Chips::fromAmount(1000), $player);

        $expectedPlayers = PlayerCollection::make([
            $player,
        ]);

        $this->assertInstanceOf(ChipPot::class, $chipPot);
        $this->assertEquals(1000, $chipPot->total()->amount());
        $this->assertEquals($expectedPlayers, $chipPot->players());
    }
}
