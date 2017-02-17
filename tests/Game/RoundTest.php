<?php

namespace Cysha\Casino\Holdem\Tests\Game;

use Ramsey\Uuid\Uuid;
use Cysha\Casino\Cards\CardCollection;
use Cysha\Casino\Cards\Deck;
use Cysha\Casino\Holdem\Cards\Evaluators\SevenCard;
use Cysha\Casino\Cards\Hand;
use Cysha\Casino\Holdem\Cards\Results\SevenCardResult;
use Cysha\Casino\Holdem\Cards\SevenCardResultCollection;
use Cysha\Casino\Game\Client;
use Cysha\Casino\Holdem\Game\Action;
use Cysha\Casino\Holdem\Game\ActionCollection;
use Cysha\Casino\Holdem\Game\CashGame;
use Cysha\Casino\Game\Chips;
use Cysha\Casino\Holdem\Game\Dealer;
use Cysha\Casino\Game\Game;
use Cysha\Casino\Holdem\Game\Player;
use Cysha\Casino\Game\PlayerCollection;
use Cysha\Casino\Holdem\Game\Round;
use Cysha\Casino\Holdem\Game\Table;

class RoundTest extends BaseGameTestCase
{
    /** @test */
    public function it_can_start_a_round_on_a_table()
    {
        $game = $this->createGenericGame();

        $round = Round::start($game->tables()->first());

        $this->assertCount(4, $round->players());
    }

    /** @test */
    public function the_button_starts_with_the_first_player()
    {
        $game = $this->createGenericGame();

        $table = $game->tables()->first();
        $round = Round::start($table);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
    }

    /** @test */
    public function the_second_player_is_the_small_blind()
    {
        $game = $this->createGenericGame();

        $table = $game->tables()->first();
        $round = Round::start($table);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
        $player2 = $table->players()->get(1);
        $this->assertEquals($round->playerWithSmallBlind(), $player2);
    }

    /** @test */
    public function the_third_player_is_the_big_blind()
    {
        $game = $this->createGenericGame();

        $table = $game->tables()->first();
        $round = Round::start($table);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
        $player3 = $table->players()->get(2);
        $this->assertEquals($round->playerWithBigBlind(), $player3);
    }

    /** @test */
    public function the_small_blind_is_moved_when_the_second_player_sit_out()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();

        $table->sitPlayerOut($table->playersSatDown()->get(1));
        $round = Round::start($table);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
        $player3 = $table->playersSatDown()->get(1);
        $this->assertEquals($round->playerWithSmallBlind(), $player3);
    }

    /** @test */
    public function the_big_blind_is_moved_when_the_third_player_sit_out()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();

        $table->sitPlayerOut($table->playersSatDown()->get(2));
        $round = Round::start($table);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
        $player3 = $table->playersSatDown()->get(2);
        $this->assertEquals($round->playerWithBigBlind(), $player3);
    }

    /** @test */
    public function the_small_blind_is_moved_to_the_fourth_player_if_player_2_and_3_sit_out()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();

        $table->sitPlayerOut($table->players()->get(1)); // player 2
        $table->sitPlayerOut($table->players()->get(2)); // player 3
        $round = Round::start($table);

        $player = $table->playersSatDown()->get(0);
        $this->assertEquals($round->playerWithSmallBlind(), $player);
    }

    /** @test */
    public function if_there_are_only_2_players_then_the_player_with_button_is_small_blind()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();

        $table->sitPlayerOut($table->players()->get(2)); // player 3
        $table->sitPlayerOut($table->players()->get(3)); // player 4
        $round = Round::start($table);

        $player1 = $table->playersSatDown()->get(0);
        $this->assertEquals($round->playerWithButton(), $player1, 'Button is with the wrong player');
        $this->assertEquals($round->playerWithSmallBlind(), $player1, 'small blind is with the wrong player');

        $player2 = $table->playersSatDown()->get(1);
        $this->assertEquals($round->playerWithBigBlind(), $player2, 'big blind is with the wrong player');
    }

    /** @test */
    public function button_will_start_on_first_sat_down_player()
    {
        $xLink = Client::register('xLink', Chips::fromAmount(5500));
        $jesus = Client::register('jesus', Chips::fromAmount(5500));
        $melk = Client::register('melk', Chips::fromAmount(5500));
        $bob = Client::register('bob', Chips::fromAmount(5500));
        $blackburn = Client::register('blackburn', Chips::fromAmount(5500));

        // we got a game
        $game = CashGame::setUp(Uuid::uuid4(), 'Demo Cash Game', Chips::fromAmount(500));

        // register clients to game
        $game->registerPlayer($xLink, Chips::fromAmount(5000)); // x
        $game->registerPlayer($jesus, Chips::fromAmount(5000)); //
        $game->registerPlayer($melk, Chips::fromAmount(5000)); // x
        $game->registerPlayer($bob, Chips::fromAmount(5000)); //
        $game->registerPlayer($blackburn, Chips::fromAmount(5000)); //

        $game->assignPlayersToTables(); // table has max of 9 or 5 players in holdem

        /** @var Table $table */
        $table = $game->tables()->first();
        $table->sitPlayerOut($table->players()->get(0)); // player 1
        $table->sitPlayerOut($table->players()->get(2)); // player 3

        $round = Round::start($table);

        $player2 = $table->players()->get(1);
        $this->assertEquals($round->playerWithButton(), $player2, 'Button is with the wrong player');
        $player4 = $table->players()->get(3);
        $this->assertEquals($round->playerWithSmallBlind(), $player4, 'small blind is with the wrong player');

        $player5 = $table->players()->get(4);
        $this->assertEquals($round->playerWithBigBlind(), $player5, 'big blind is with the wrong player');
    }

    /** @test */
    public function small_blind_from_player_gets_posted_and_added_to_the_pot()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();
        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);

        $round = Round::start($table);
        /*
        [
            xLink: 0, // button
            jesus: 25, // SB
            melk: 50, // BB
            bob: 0,
        ]
        */

        $round->postSmallBlind($player2);
        $this->assertEquals(Chips::fromAmount(25), $round->playerBetStack($player2));

        $round->postBigBlind($player3);
        $this->assertEquals(Chips::fromAmount(50), $round->playerBetStack($player3));
    }

    /** @test */
    public function on_round_start_deal_hands()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();
        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $round = Round::start($table);

        $round->dealHands();

        $this->assertCount(2, $round->playerHand($player1));
        $this->assertCount(2, $round->playerHand($player2));
        $this->assertCount(2, $round->playerHand($player3));
        $this->assertCount(2, $round->playerHand($player4));
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function on_round_start_stood_up_players_dont_get_dealt_a_hand()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();
        $player4 = $table->playersSatDown()->get(3);

        $table->sitPlayerOut($player4);

        $round = Round::start($table);

        $round->dealHands();

        // This should throw an exception
        $round->playerHand($player4);
    }

    /** @test */
    public function fifth_player_in_proceedings_is_prompted_to_action_after_round_start_when_player_4_is_stood_up()
    {
        $game = $this->createGenericGame(5);

        /** @var Table $table */
        $table = $game->tables()->first();
        $player1 = $table->playersSatDown()->first(); // Button
        $player2 = $table->playersSatDown()->get(1); // SB
        $player3 = $table->playersSatDown()->get(2); // BB
        $player4 = $table->playersSatDown()->get(3); // x [Sat out]
        $player5 = $table->playersSatDown()->get(4); // [turn]

        $round = Round::start($table);

        $round->sitPlayerOut($player4);

        $round->postSmallBlind($player2);
        $round->postBigBlind($player3);

        $this->assertEquals($player5, $round->whosTurnIsIt());
    }

    /** @test */
    public function fourth_player_calls_the_hand_after_blinds_are_posted()
    {
        $game = $this->createGenericGame(5);

        /** @var Table $table */
        $table = $game->tables()->first();
        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first(); // Button
        $player2 = $table->playersSatDown()->get(1); // SB
        $player4 = $table->playersSatDown()->get(3); // x [Sat out]

        $round = Round::start($table);

        $round->postSmallBlind($player1);
        $round->postBigBlind($player2);

        $round->playerCalls($player4);

        $this->assertEquals(50, $round->playerBetStack($player4)->amount());
        $this->assertEquals(950, $player4->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(3)->chipStack()->amount());
        $this->assertEquals(125, $round->betStacks()->total()->amount());
    }

    /** @test */
    public function player_pushes_all_in()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();
        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $round = Round::start($table);

        $round->postSmallBlind($player2); // 25
        $round->postBigBlind($player3); // 50

        $round->playerCalls($player4); // 50
        $round->playerPushesAllIn($player1); // 1000

        $this->assertEquals(1000, $round->playerBetStack($player1)->amount());
        $this->assertEquals(0, $player1->chipStack()->amount());
        $this->assertEquals(0, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(1125, $round->betStacks()->total()->amount());
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function fifth_player_tries_to_raise_the_hand_after_blinds_without_enough_chips()
    {
        $game = $this->createGenericGame(5);

        /** @var Table $table */
        $table = $game->tables()->first();

        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player4 = $table->playersSatDown()->get(3);
        $player5 = $table->playersSatDown()->get(4);

        $round = Round::start($table);

        $round->postSmallBlind($player1);
        $round->postBigBlind($player2);

        $round->playerCalls($player4);
        $round->playerRaises($player5, Chips::fromAmount(100000));
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function random_player_tries_to_fold_their_hand_after_blinds()
    {
        $game = $this->createGenericGame(5);

        /** @var Table $table */
        $table = $game->tables()->first();

        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $randomPlayer = Player::fromClient(Client::register('Random Player', Chips::fromAmount(1)));

        $round = Round::start($table);

        $round->postSmallBlind($player1);
        $round->postBigBlind($player2);
        $round->playerFoldsHand($randomPlayer);
    }

    /** @test */
    public function button_player_folds_their_hand()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();

        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1); // SB - 25
        $player3 = $table->playersSatDown()->get(2); // BB - 50
        $player4 = $table->playersSatDown()->get(3); // Call - 50

        $round = Round::start($table);

        $round->postSmallBlind($player2);
        $round->postBigBlind($player3);

        $round->playerCalls($player4);
        $round->playerFoldsHand($player1);

        $this->assertEquals(125, $round->betStacks()->total()->amount());
        $this->assertCount(3, $round->playersStillIn());
        $this->assertFalse($round->playerIsStillIn($player1));
    }

    /** @test */
    public function can_confirm_it_is_player_after_big_blinds_turn()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1); // SB - 25
        $seat3 = $table->playersSatDown()->get(2); // BB - 50
        $seat4 = $table->playersSatDown()->get(3); // Call - 50

        $round = Round::start($table);

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $this->assertEquals($seat4, $round->whosTurnIsIt());
        $round->playerCalls($seat4);

        $this->assertEquals($seat1, $round->whosTurnIsIt());
        $round->playerFoldsHand($seat1);

        $this->assertEquals($seat2, $round->whosTurnIsIt());
        $round->playerCalls($seat2);

        $this->assertEquals($seat3, $round->whosTurnIsIt());
        $round->playerCalls($seat3);

        // no one else has to action
        $this->assertEquals(false, $round->whosTurnIsIt());
    }

    /** @test */
    public function can_confirm_whos_turn_it_is_with_all_ins()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1); // SB - 25
        $seat3 = $table->playersSatDown()->get(2); // BB - 50
        $seat4 = $table->playersSatDown()->get(3); // Call - 50

        $round = Round::start($table);

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $this->assertEquals($seat4, $round->whosTurnIsIt());
        $round->playerPushesAllIn($seat4);

        $this->assertEquals($seat1, $round->whosTurnIsIt());
        $round->playerFoldsHand($seat1);

        $this->assertEquals($seat2, $round->whosTurnIsIt());
        $round->playerPushesAllIn($seat2);

        $this->assertEquals($seat3, $round->whosTurnIsIt());
        $round->playerFoldsHand($seat3);

        // no one else has to action
        $this->assertEquals(false, $round->whosTurnIsIt());
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_flop_whilst_players_still_have_to_act()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();

        $round = Round::start($table);

        $round->dealHands();

        $round->dealFlop();
    }

    /** @test */
    public function a_round_has_a_flop()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();
        $this->assertCount(1, $round->burnCards());
        $this->assertCount(3, $round->communityCards());
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_flop_more_than_once_a_round()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();
        $round->dealFlop();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_turn_before_the_flop()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();

        $round = Round::start($table);

        $round->dealHands();

        $round->dealTurn();
    }

    /** @test */
    public function a_round_has_a_turn()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_turn_more_than_once_per_round()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();
        $round->dealTurn();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_turn_when_players_have_still_to_act()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat1);

        $round->dealTurn();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_river_before_flop_or_turn()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();

        $round = Round::start($table);

        $round->dealHands();

        $round->dealRiver();
    }

    /** @test */
    public function a_round_has_a_river()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealRiver();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_river_more_than_once_per_round()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealRiver();
        $round->dealRiver();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_river_when_players_have_still_to_act()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat1);

        $round->dealRiver();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_get_the_winning_players_without_ending_the_round()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $round = Round::start($table);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealRiver();
        $round->winningPlayer();
    }

    /** @test */
    public function can_get_a_list_of_actions()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $round = Round::start($table);

        // deal some hands
        $round->dealHands();

        $round->postSmallBlind($player2); // 25
        $round->postBigBlind($player3); // 50

        $round->playerCalls($player4); // 50
        $round->playerFoldsHand($player1);
        $round->playerCalls($player2); // SB + 25
        $round->playerChecks($player3); // BB

        $expected = ActionCollection::make([
            new Action($player2, Action::SMALL_BLIND, Chips::fromAmount(25)),
            new Action($player3, Action::BIG_BLIND, Chips::fromAmount(50)),
            new Action($player4, Action::CALL, Chips::fromAmount(50)),
            new Action($player1, Action::FOLD),
            new Action($player2, Action::CALL, Chips::fromAmount(25)),
            new Action($player3, Action::CHECK),
        ]);
        $this->assertEquals($expected, $round->playerActions());
    }

    /** @test */
    public function a_round_can_be_completed()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $round = Round::start($table);

        // deal some hands
        $round->dealHands();

        // make sure we start with no chips on the table
        $this->assertEquals(0, $round->betStacksTotal());

        $round->postSmallBlind($player2); // 25
        $round->postBigBlind($player3); // 50

        $round->playerCalls($player4); // 50
        $round->playerFoldsHand($player1);
        $round->playerCalls($player2); // SB + 25
        $round->playerChecks($player3); // BB

        $this->assertEquals(150, $round->betStacksTotal());
        $this->assertCount(3, $round->playersStillIn());
        $this->assertEquals(1000, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(1)->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(2)->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(3)->chipStack()->amount());

        // collect the chips, burn a card, deal the flop
        $round->dealFlop();
        $this->assertEquals(150, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerChecks($player2); // 0
        $round->playerRaises($player3, Chips::fromAmount(250)); // 250
        $round->playerCalls($player4); // 250
        $round->playerFoldsHand($player2);

        $this->assertEquals(500, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(950, $round->players()->get(1)->chipStack()->amount());
        $this->assertEquals(700, $round->players()->get(2)->chipStack()->amount());
        $this->assertEquals(700, $round->players()->get(3)->chipStack()->amount());

        // collect chips, burn 1, deal 1
        $round->dealTurn();

        $this->assertEquals(650, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerRaises($player3, Chips::fromAmount(450)); // 450
        $round->playerCalls($player4); // 450

        $this->assertEquals(900, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(250, $round->players()->get(2)->chipStack()->amount());
        $this->assertEquals(250, $round->players()->get(3)->chipStack()->amount());

        // collect chips, burn 1, deal 1
        $round->dealRiver();
        $this->assertEquals(1550, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerPushesAllIn($player3); // 250
        $round->playerCalls($player4); // 250

        $round->end();
        $this->assertEquals(2050, $round->chipPots()->get(0)->total()->amount());
        $this->assertEquals(2050, $round->chipPots()->total()->amount());
        $this->assertEquals(0, $round->betStacksTotal());
    }

    /** @test */
    public function a_headsup_round_can_be_completed()
    {
        $game = $this->createGenericGame(2);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);

        $round = Round::start($table);

        // deal some hands
        $round->dealHands();

        // make sure we start with no chips on the table
        $this->assertEquals(0, $round->betStacksTotal());

        $round->postSmallBlind($player1); // 25
        $round->postBigBlind($player2); // 50
        $round->playerCalls($player1); // 25
        $round->playerChecks($player2);

        $this->assertEquals(100, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(950, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(1)->chipStack()->amount());

        // collect the chips, burn a card, deal the flop
        $round->dealFlop();
        $this->assertEquals(100, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerChecks($player1); // 0
        $round->playerRaises($player2, Chips::fromAmount(250)); // 250
        $round->playerCalls($player1); // 250

        $this->assertEquals(500, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(700, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(700, $round->players()->get(1)->chipStack()->amount());

        // collect chips, burn 1, deal 1
        $round->dealTurn();
        $this->assertEquals(600, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerRaises($player1, Chips::fromAmount(450)); // 450
        $round->playerCalls($player2); // 450

        $this->assertEquals(900, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(250, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(250, $round->players()->get(1)->chipStack()->amount());

        // collect chips, burn 1, deal 1
        $round->dealRiver();
        $this->assertEquals(1500, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerChecks($player1); // 0
        $round->playerPushesAllIn($player2); // 250
        $round->playerCalls($player1); // 250

        $round->collectChipTotal();
        $this->assertEquals(2000, $round->currentPot()->totalAmount());
        $round->end();
        $this->assertEquals(0, $round->betStacksTotal());
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function player_cant_call_out_of_turn()
    {
        $game = $this->createGenericGame(2);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);

        $round = Round::start($table);

        // deal some hands
        $round->dealHands();

        $round->postSmallBlind($player1); // 25
        $round->postBigBlind($player2); // 50
        $round->playerChecks($player2); // 50
    }

    /** @test */
    public function winning_player_get_entire_pot_added_to_chipstack()
    {
        $client1 = Client::register('player1', Chips::fromAmount(5500));
        $client2 = Client::register('player2', Chips::fromAmount(5500));
        $client3 = Client::register('player3', Chips::fromAmount(5500));
        $player1 = Player::fromClient($client1, Chips::fromAmount(5500));
        $player2 = Player::fromClient($client2, Chips::fromAmount(5500));
        $player3 = Player::fromClient($client3, Chips::fromAmount(5500));

        $players = PlayerCollection::make([
            $player1,
            $player2,
            $player3,
        ]);

        $board = CardCollection::fromString('3s 3h 8h 2s 4c');
        $winningHand = Hand::createUsingString('As Ad', $player1);

        /** @var SevenCard $evaluator */
        $evaluator = $this->createMock(SevenCard::class);
        $evaluator->method('evaluateHands')
                  ->with($this->anything(), $this->anything())
                  ->will($this->returnValue(SevenCardResultCollection::make([
                      SevenCardResult::createTwoPair($board->merge($winningHand->cards()), $winningHand),
                  ])))
        ;

        // Do game
        $dealer = Dealer::startWork(new Deck(), $evaluator);
        $table = Table::setUp($dealer, $players);

        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);

        $round = Round::start($table);

        $this->dealHandsAndPlayGame($round, $seat2, $seat3, $seat1);

        $this->assertEquals(150, $round->currentPot()->totalAmount());
        $round->end();

        $this->assertEquals($winningHand->player(), $round->winningPlayer());
        $this->assertEquals(150, $round->chipPots()->get(0)->totalAmount());
        $this->assertEquals(0, $round->currentPot()->totalAmount());
        $this->assertEquals(5600, $round->players()->get(0)->chipStack()->amount());
    }

    /** @test */
    public function split_pot_with_3_players_new()
    {
        $players = PlayerCollection::make([
            Player::fromClient(Client::register('xLink', Chips::fromAmount(800)), Chips::fromAmount(800)),
            Player::fromClient(Client::register('jesus', Chips::fromAmount(300)), Chips::fromAmount(300)),
            Player::fromClient(Client::register('melk', Chips::fromAmount(150)), Chips::fromAmount(150)),
        ]);
        $xLink = $players->first();
        $jesus = $players->get(1);
        $melk = $players->get(2);

        $board = CardCollection::fromString('3s 3h 8h 2s 4c');
        $winningHand = Hand::createUsingString('As Ad', $xLink);

        /** @var SevenCard $evaluator */
        $evaluator = $this->createMock(SevenCard::class);
        $evaluator->method('evaluateHands')
            ->with($this->anything(), $this->anything())
            ->will($this->returnValue(SevenCardResultCollection::make([
                SevenCardResult::createTwoPair($board->merge($winningHand->cards()), $winningHand),
            ])))
        ;

        // Do game
        $dealer = Dealer::startWork(new Deck(), $evaluator);
        $table = Table::setUp($dealer, $players);

        $round = Round::start($table);

        $round->postSmallBlind($jesus); // 25
        $round->postBigBlind($melk); // 50

        $round->playerPushesAllIn($xLink); // 150
        $round->playerPushesAllIn($jesus); // SB + 275  (300)

        $round->playerPushesAllIn($melk); // 800 (300)

        $this->assertEquals(800, $round->betStacks()->findByPlayer($xLink)->amount());
        $this->assertEquals(300, $round->betStacks()->findByPlayer($jesus)->amount());
        $this->assertEquals(150, $round->betStacks()->findByPlayer($melk)->amount());

        $round->end();

        /*
        xLink: 800, Jesus: 300, Melk: 150,

        Pot1: (melk smallest...) melk -150, jesus -150, xlink -150 = 450
            xLink: 650, Jesus: 150, Melk: 0

        Pot2: (jesus smallest...)  jesus -150, xlink -150 = 300
            xLink: 500, Jesus: 0

        Pot3: xLink w/ 500
        */
        $this->assertEquals(450, $round->chipPots()->get(0)->total()->amount());
        $this->assertEquals(300, $round->chipPots()->get(1)->total()->amount());
        $this->assertEquals(500, $round->chipPots()->get(2)->total()->amount());
    }

    /** @test */
    public function test_the_oshit_scenario()
    {
        $players = PlayerCollection::make([
            Player::fromClient(Client::register('xLink', Chips::fromAmount(650)), Chips::fromAmount(2000)),
            Player::fromClient(Client::register('jesus', Chips::fromAmount(800)), Chips::fromAmount(300)),
            Player::fromClient(Client::register('melk', Chips::fromAmount(1200)), Chips::fromAmount(800)),
            Player::fromClient(Client::register('bob', Chips::fromAmount(1200)), Chips::fromAmount(150)),
            Player::fromClient(Client::register('blackburn', Chips::fromAmount(1200)), Chips::fromAmount(5000)),
        ]);
        $xLink = $players->get(0);
        $jesus = $players->get(1);
        $melk = $players->get(2);
        $bob = $players->get(3);
        $blackburn = $players->get(4);

        $board = CardCollection::fromString('3s 3h 8h 2s 4c');
        $winningHand = Hand::createUsingString('As Ad', $xLink);

        /** @var SevenCard $evaluator */
        $evaluator = $this->createMock(SevenCard::class);
        $evaluator->method('evaluateHands')
            ->with($this->anything(), $this->anything())
            ->will($this->returnValue(SevenCardResultCollection::make([
                SevenCardResult::createTwoPair($board->merge($winningHand->cards()), $winningHand),
            ])))
        ;

        // Do game
        $dealer = Dealer::startWork(new Deck(), $evaluator);
        $table = Table::setUp($dealer, $players);

        $round = Round::start($table);

        $round->postSmallBlind($jesus); // 25
        $round->postBigBlind($melk); // 50

        $round->playerPushesAllIn($bob); // 150
        $round->playerFoldsHand($blackburn); // 0
        $round->playerPushesAllIn($xLink); // 2000 (300)
        $round->playerPushesAllIn($jesus); // SB + 275
        $round->playerFoldsHand($melk); // 0

        $this->assertEquals(2000, $round->betStacks()->findByPlayer($xLink)->amount());
        $this->assertEquals(300, $round->betStacks()->findByPlayer($jesus)->amount());
        $this->assertEquals(50, $round->betStacks()->findByPlayer($melk)->amount());
        $this->assertEquals(150, $round->betStacks()->findByPlayer($bob)->amount());
        $this->assertEquals(0, $round->betStacks()->findByPlayer($blackburn)->amount());

        $round->end();

        /*
        xLink: 2000, Jesus: 300, Melk: 50, BOB: 150

        Pot1: (melk smallest...) melk -50, bob -50, jesus -50, xlink -50 = 200
            xLink: 1950, Jesus: 250, Melk: 0, BOB: 100

        Pot2: (bob smallest...) bob -100, jesus -100, xlink -100 = 300
            xLink: 1850, Jesus: 150, BOB: 0

        Pot3: (jesus smallest...) jesus -150, xlink -150 = 300
            xLink: 1700, Jesus: 0

        Pot4: xLink w/ 1700

        */

        $this->assertEquals(200, $round->chipPots()->get(0)->total()->amount());
        $this->assertEquals(300, $round->chipPots()->get(1)->total()->amount());
        $this->assertEquals(300, $round->chipPots()->get(2)->total()->amount());
        $this->assertEquals(1700, $round->chipPots()->get(3)->total()->amount());
    }

    /**
     * @param $round
     * @param $seat2
     * @param $seat3
     * @param $seat1
     */
    private function dealHandsAndPlayGame(Round $round, $seat2, $seat3, $seat1)
    {
        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat1);

        $round->dealTurn();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat1);

        $round->dealRiver();
    }
}
