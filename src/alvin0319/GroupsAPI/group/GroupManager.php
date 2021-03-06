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

namespace alvin0319\GroupsAPI\group;

use alvin0319\GroupsAPI\GroupsAPI;
use Generator;
use JsonException;
use SOFe\AwaitGenerator\Await;
use function array_values;
use function json_encode;

final class GroupManager{

	protected GroupsAPI $plugin;
	/** @var Group[] */
	protected array $groups = [];

	public function __construct(){
		$this->plugin = GroupsAPI::getInstance();
	}

	public function registerGroup(string $name, int $priority, array $permissions, bool $sync = false) : Generator{
		$group = new Group($name, $permissions, $priority);
		$this->groups[$group->getName()] = $group;
		if($sync){
			try{
				yield from GroupsAPI::getDatabase()->createGroup($name, json_encode($permissions, JSON_THROW_ON_ERROR), $priority);
			}catch(JsonException $e){
			}
		}
	}

	public function unregisterGroup(Group $group, bool $sync = false) : Generator{
		unset($this->groups[$group->getName()]);
		foreach(GroupsAPI::getInstance()->getMemberManager()->getMembers() as $member){
			yield from $member->onGroupRemoved($group);
		}
		if($sync){
			yield from GroupsAPI::getDatabase()->deleteGroup($group->getName());
		}
	}

	public function updateGroup(Group $group) : void{
		Await::f2c(function() use ($group) : Generator{
			$affectedRows = yield from GroupsAPI::getDatabase()->updateGroup($group->getName(), json_encode($group->getPermissions(), JSON_THROW_ON_ERROR), $group->getPriority());
			if($affectedRows > 0){
				$this->plugin->getLogger()->debug("Updated group {$group->getName()}");
			}
		});
	}

	public function getGroup(string $name) : ?Group{
		return $this->groups[$name] ?? null;
	}

	/** @return Group[] */
	public function getGroups() : array{
		return array_values($this->groups);
	}
}