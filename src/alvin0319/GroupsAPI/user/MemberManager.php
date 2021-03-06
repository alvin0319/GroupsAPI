<?php

/**
 * ____                              _    ____ ___
 * / ___|_ __ ___  _   _ _ __  ___   / \  |  _ \_ _|
 * | |  _| '__/ _ \| | | | '_ \/ __| / _ \ | |_) | |
 * | |_| | | | (_) | |_| | |_) \__ \/ ___ \|  __/| |
 * \____|_|  \___/ \__,_| .__/|___/_/   \_\_|  |___|
 * |_|
 *
 * MIT License
 *
 * Copyright (c) 2022 alvin0319
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @noinspection PhpUnusedParameterInspection
 */

declare(strict_types=1);

namespace alvin0319\GroupsAPI\user;

use alvin0319\GroupsAPI\GroupsAPI;
use Generator;
use SOFe\AwaitGenerator\Await;
use function array_values;
use function count;
use function json_encode;
use function strtolower;

final class MemberManager{

	/** @var Member[] */
	protected array $members = [];

	protected GroupsAPI $plugin;

	public function __construct(){
		$this->plugin = GroupsAPI::getInstance();
	}

	/**
	 * @param string $name
	 * @param bool   $createOnMissing
	 *
	 * @return Generator<Member|null>
	 */
	public function loadMember(string $name, bool $createOnMissing = false) : Generator{
		if(isset($this->members[$name = strtolower($name)])){
			return $this->members[$name];
		}

		$rows = yield from GroupsAPI::getDatabase()->getUser($name);
		if(count($rows) > 0){
			$member = new Member(strtolower($rows[0]["name"]), []);
			yield from $this->registerMember($member);
			return $member;
		}elseif($createOnMissing){
			$groups = GroupsAPI::getInstance()->getDefaultGroups();
			$defaultGroups = [];
			foreach($groups as $group){
				$defaultGroups[$group] = null;
			}
			$member = new Member($name, $defaultGroups);
			$member->setLoaded(true);
			$member->onLoad();
			yield from $this->registerMember($member, true);
			return $member;
		}
		return null;
	}

	public function registerMember(Member $member, bool $sync = false) : Generator{
		$this->members[strtolower($member->getName())] = $member;
		$this->plugin->getLogger()->debug("Registered member: " . $member->getName());
		if($sync){
			yield from GroupsAPI::getDatabase()->createUser($member->getName(), json_encode($member->getMappedGroups(), JSON_THROW_ON_ERROR));
		}
	}

	/** @return Member[] */
	public function getMembers() : array{
		return array_values($this->members);
	}

	public function unloadMember(Member $member) : void{
		Await::g2c($member->updateGroups());
		Await::f2c(function() use ($member) : Generator{
			yield from GroupsAPI::getDatabase()->updateState($member->getName(), 0);
			$this->plugin->getLogger()->debug("Updated state of user {$member->getName()} to 0");
		});
		unset($this->members[$member->getName()]);
	}

	public function getMember(string $name) : ?Member{
		return $this->members[strtolower($name)] ?? null;
	}

	public function schedule() : void{
		foreach($this->members as $name => $member){
			if($member->isLoaded()){
				if($member->getPlayer() === null){
					$this->unloadMember($member);
					continue;
				}
			}
			$member->tick();
		}
	}

	public function close() : void{
		foreach($this->members as $name => $member){
			$this->unloadMember($member);
		}
	}
}